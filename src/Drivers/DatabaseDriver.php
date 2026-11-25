<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Drivers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\DTOs\AnalyticsEventData;
use Proflujo\Analytics\Models\AnalyticsEvent;
use Proflujo\Analytics\Support\DateRange;
use Proflujo\Analytics\Support\EventDefinitionRegistry;
use stdClass;

final class DatabaseDriver implements AnalyticsDriver
{
    private const string UTC_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private readonly EventDefinitionRegistry $registry) {}

    public function insert(AnalyticsEventData $event): void
    {
        $this->connection()->table('analytics_events')->insert([
            'event_name' => $event->eventName,
            'category' => $event->category,
            'user_id' => $event->userId,
            'properties' => json_encode($event->properties === [] ? new stdClass : $event->properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'occurred_at' => $event->occurredAt->utc()->format(self::UTC_FORMAT),
            'created_at' => CarbonImmutable::now('UTC')->format(self::UTC_FORMAT),
        ]);
    }

    public function paginateEvents(DateRange $range, array $eventNames, ?string $category, int $perPage, int $page): LengthAwarePaginator
    {
        $query = AnalyticsEvent::query()
            ->where('occurred_at', '>=', $range->utcStart()->format(self::UTC_FORMAT))
            ->where('occurred_at', '<', $range->utcEnd()->format(self::UTC_FORMAT));

        if ($eventNames !== []) {
            $query->whereIn('event_name', $eventNames);
        }

        if ($category !== null && $category !== '') {
            $this->applyCategory($query, $category);
        }

        return $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function countDay(DateRange $day): array
    {
        $rows = $this->connection()->table('analytics_events')
            ->selectRaw('event_name, COUNT(*) as event_count, COUNT(DISTINCT user_id) as unique_users')
            ->where('occurred_at', '>=', $day->utcStart()->format(self::UTC_FORMAT))
            ->where('occurred_at', '<', $day->utcEnd()->format(self::UTC_FORMAT))
            ->groupBy('event_name')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $users = (int) $row->unique_users;
            $result[] = [
                'event_name' => (string) $row->event_name,
                'event_count' => (int) $row->event_count,
                'unique_users' => $users > 0 ? $users : null,
            ];
        }

        return $result;
    }

    public function storeAggregates(string $date, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
        $records = array_map(static fn (array $row): array => [
            'event_name' => $row['event_name'],
            'date' => $date,
            'event_count' => $row['event_count'],
            'unique_users' => $row['unique_users'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        $this->connection()->transaction(function () use ($records): void {
            foreach (array_chunk($records, 500) as $chunk) {
                $this->connection()->table('analytics_daily_aggregates')
                    ->upsert($chunk, ['event_name', 'date'], ['event_count', 'unique_users', 'updated_at']);
            }
        });
    }

    public function aggregatedCounts(DateRange $range, array $eventNames = []): array
    {
        $query = $this->connection()->table('analytics_daily_aggregates')
            ->select(['event_name', 'date', 'event_count'])
            ->where('date', '>=', $range->from->toDateString())
            ->where('date', '<=', $range->to->toDateString());

        if ($eventNames !== []) {
            $query->whereIn('event_name', $eventNames);
        }

        $counts = [];

        foreach ($query->get() as $row) {
            $counts[(string) $row->event_name][substr((string) $row->date, 0, 10)] = (int) $row->event_count;
        }

        return $counts;
    }

    public function eventNamesWithCategory(DateRange $range, string $category): array
    {
        return array_values($this->connection()->table('analytics_events')
            ->where('category', $category)
            ->where('occurred_at', '>=', $range->utcStart()->format(self::UTC_FORMAT))
            ->where('occurred_at', '<', $range->utcEnd()->format(self::UTC_FORMAT))
            ->distinct()
            ->pluck('event_name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all());
    }

    public function storedCategories(DateRange $range): array
    {
        return array_values($this->connection()->table('analytics_events')
            ->whereNotNull('category')
            ->where('occurred_at', '>=', $range->utcStart()->format(self::UTC_FORMAT))
            ->where('occurred_at', '<', $range->utcEnd()->format(self::UTC_FORMAT))
            ->distinct()
            ->pluck('category')
            ->map(static fn (mixed $category): string => (string) $category)
            ->all());
    }

    public function oldestEventAt(): ?CarbonImmutable
    {
        $oldest = $this->connection()->table('analytics_events')->min('occurred_at');

        return $oldest === null ? null : CarbonImmutable::parse((string) $oldest, 'UTC');
    }

    public function pruneEventsBefore(CarbonImmutable $before, int $batchSize): int
    {
        $deleted = 0;
        $cutoff = $before->utc()->format(self::UTC_FORMAT);

        do {
            $ids = $this->connection()->table('analytics_events')
                ->where('occurred_at', '<', $cutoff)
                ->limit(max(1, $batchSize))
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += $this->connection()->table('analytics_events')->whereIn('id', $ids->all())->delete();
        } while (true);

        return $deleted;
    }

    private function connection(): ConnectionInterface
    {
        $name = config('analytics.database.connection');

        return DB::connection(is_string($name) && $name !== '' ? $name : null);
    }

    /** @param  Builder<AnalyticsEvent>  $query */
    private function applyCategory(Builder $query, string $category): void
    {
        if ($category === EventDefinitionRegistry::UNCATEGORIZED) {
            $query->whereNotIn('event_name', $this->registry->categorizedNames())
                ->where(fn (Builder $q) => $q->whereNull('category')->orWhere('category', $category));

            return;
        }

        $query->where(fn (Builder $q) => $q
            ->whereIn('event_name', $this->registry->namesInCategory($category))
            ->orWhere('category', $category));
    }
}
