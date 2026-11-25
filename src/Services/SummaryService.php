<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Services;

use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\Support\DateRange;
use Proflujo\Analytics\Support\EventDefinitionRegistry;

final class SummaryService
{
    /** Today and yesterday are read live from raw events: aggregation may not have run yet. */
    private const int LIVE_DAYS = 2;

    public function __construct(
        private readonly AnalyticsDriver $driver,
        private readonly EventDefinitionRegistry $registry,
    ) {}

    /**
     * Daily series per event, zero-filled across the range. Only events with at least one
     * occurrence in the range are returned, ordered by total descending.
     *
     * @param  list<string>  $eventNames  Empty = all events.
     * @return array<string, array{total: int, counts: array<string, int>}>
     */
    public function series(DateRange $range, array $eventNames = [], ?string $category = null): array
    {
        $counts = $this->driver->aggregatedCounts($range, $eventNames);

        $liveFrom = DateRange::today()->subDays(self::LIVE_DAYS - 1);

        foreach ($range->dates() as $date) {
            if ($date < $liveFrom->toDateString() || $date > DateRange::today()->toDateString()) {
                continue;
            }

            foreach ($counts as &$byDate) {
                unset($byDate[$date]); // live data replaces any stale aggregate for this day
            }
            unset($byDate);

            foreach ($this->driver->countDay($range->dayRange($date)) as $row) {
                if ($eventNames === [] || in_array($row['event_name'], $eventNames, true)) {
                    $counts[$row['event_name']][$date] = $row['event_count'];
                }
            }
        }

        if ($category !== null && $category !== '') {
            $allowed = $this->namesForCategory($range, $category, array_keys($counts));
            $counts = array_intersect_key($counts, array_flip($allowed));
        }

        $dates = $range->dates();
        $series = [];

        foreach ($counts as $event => $byDate) {
            $filled = [];
            foreach ($dates as $date) {
                $filled[$date] = $byDate[$date] ?? 0;
            }

            $total = array_sum($filled);

            if ($total > 0) {
                $series[$event] = ['total' => $total, 'counts' => $filled];
            }
        }

        uasort($series, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $series;
    }

    /**
     * @return array{from: string, to: string, total: int, event_types: int, events: array<string, int>}
     */
    public function summary(DateRange $range): array
    {
        $events = array_map(static fn (array $s): int => $s['total'], $this->series($range));

        return [
            'from' => $range->from->toDateString(),
            'to' => $range->to->toDateString(),
            'total' => array_sum($events),
            'event_types' => count($events),
            'events' => $events,
        ];
    }

    /**
     * @param  list<int|string>  $candidates
     * @return list<string>
     */
    private function namesForCategory(DateRange $range, string $category, array $candidates): array
    {
        $names = array_map(strval(...), $candidates);

        if ($category === EventDefinitionRegistry::UNCATEGORIZED) {
            return array_values(array_filter($names, fn (string $n): bool => $this->registry->category($n) === $category));
        }

        $allowed = array_merge(
            $this->registry->namesInCategory($category),
            $this->driver->eventNamesWithCategory($range, $category),
        );

        return array_values(array_intersect($names, $allowed));
    }
}
