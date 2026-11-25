<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Proflujo\Analytics\DTOs\AnalyticsEventData;
use Proflujo\Analytics\Models\AnalyticsEvent;
use Proflujo\Analytics\Support\DateRange;

interface AnalyticsDriver
{
    public function insert(AnalyticsEventData $event): void;

    /**
     * Paginated raw events, newest first.
     *
     * @param  list<string>  $eventNames  Empty = all events.
     * @return LengthAwarePaginator<int, AnalyticsEvent>
     */
    public function paginateEvents(DateRange $range, array $eventNames, ?string $category, int $perPage, int $page): LengthAwarePaginator;

    /**
     * Event counts for one local calendar day, computed from raw events.
     *
     * @return list<array{event_name: string, event_count: int, unique_users: ?int}>
     */
    public function countDay(DateRange $day): array;

    /**
     * Persist aggregate rows for one local date atomically and idempotently.
     *
     * @param  list<array{event_name: string, event_count: int, unique_users: ?int}>  $rows
     */
    public function storeAggregates(string $date, array $rows): void;

    /**
     * Pre-computed daily counts: event => [Y-m-d => count].
     *
     * @param  list<string>  $eventNames  Empty = all events.
     * @return array<string, array<string, int>>
     */
    public function aggregatedCounts(DateRange $range, array $eventNames = []): array;

    /**
     * Distinct event names that were stored with the given explicit category.
     *
     * @return list<string>
     */
    public function eventNamesWithCategory(DateRange $range, string $category): array;

    /** @return list<string> Distinct explicit categories stored in the range. */
    public function storedCategories(DateRange $range): array;

    public function oldestEventAt(): ?CarbonImmutable;

    /** Delete raw events strictly before the instant, in batches. Returns rows deleted. */
    public function pruneEventsBefore(CarbonImmutable $before, int $batchSize): int;
}
