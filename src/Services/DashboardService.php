<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\Models\AnalyticsEvent;
use Proflujo\Analytics\Support\DateRange;
use Proflujo\Analytics\Support\EventDefinitionRegistry;
use Proflujo\Analytics\Support\PropertySanitizer;

/** Read-only view models for the dashboard and its JSON endpoints. */
final class DashboardService
{
    public function __construct(
        private readonly AnalyticsDriver $driver,
        private readonly SummaryService $summaries,
        private readonly EventDefinitionRegistry $registry,
    ) {}

    /**
     * @param  list<string>  $events
     * @return array{total: int, event_types: int, labels: list<string>, charts: list<array{event: string, name: string, category: string, total: int, data: list<int>}>, limited: bool, limit: int, event_options: array<string, string>}
     */
    public function charts(DateRange $range, array $events, ?string $category): array
    {
        $series = $this->summaries->series($range, [], $category);

        $options = [];
        foreach (array_keys($series) as $event) {
            $options[$event] = $this->registry->name($event);
        }

        if ($events !== []) {
            $series = array_intersect_key($series, array_flip($events));
        }

        $limit = (int) config('analytics.dashboard.max_charts', 12);
        $charts = [];

        foreach (array_slice($series, 0, $limit, true) as $event => $data) {
            $charts[] = [
                'event' => (string) $event,
                'name' => $this->registry->name((string) $event),
                'category' => $this->registry->category((string) $event),
                'total' => $data['total'],
                'data' => array_values($data['counts']),
            ];
        }

        return [
            'total' => array_sum(array_column($series, 'total')),
            'event_types' => count($series),
            'labels' => $range->dates(),
            'charts' => $charts,
            'limited' => count($series) > $limit,
            'limit' => $limit,
            'event_options' => array_slice($options, 0, 200, true),
        ];
    }

    /**
     * @param  list<string>  $events
     * @return array{rows: list<array{name: string, event: string, category: string, user_id: ?string, occurred_at: string, properties: ?string}>, paginator: LengthAwarePaginator<int, AnalyticsEvent>}
     */
    public function events(DateRange $range, array $events, ?string $category, int $page): array
    {
        $perPage = (int) config('analytics.dashboard.per_page', 25);
        $paginator = $this->driver->paginateEvents($range, $events, $category, $perPage, $page);
        $sanitizer = PropertySanitizer::fromConfig();
        $rows = [];

        foreach ($paginator->items() as $event) {
            $definition = $this->registry->resolve($event->event_name);
            $properties = $sanitizer->sanitize($event->properties ?? []);

            $rows[] = [
                'name' => $definition['name'],
                'event' => $event->event_name,
                'category' => $definition['defined'] && $definition['category'] !== EventDefinitionRegistry::UNCATEGORIZED
                    ? $definition['category']
                    : ($event->category ?? EventDefinitionRegistry::UNCATEGORIZED),
                'user_id' => $event->user_id,
                'occurred_at' => $event->occurred_at->format('Y-m-d H:i:s T'),
                'properties' => $properties === [] ? null : (json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: null),
            ];
        }

        return ['rows' => $rows, 'paginator' => $paginator];
    }

    /** @return list<string> */
    public function categories(DateRange $range): array
    {
        $categories = array_merge($this->registry->categories(), $this->driver->storedCategories($range));
        $categories = array_values(array_unique($categories));
        sort($categories);

        return $categories;
    }
}
