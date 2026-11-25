<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\Services\AggregationService;
use Proflujo\Analytics\Support\DateRange;

final class PruneEventsCommand extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Delete raw analytics events older than the retention period (aggregates are kept)';

    public function handle(AnalyticsDriver $driver, AggregationService $aggregation): int
    {
        $retention = config('analytics.aggregation.retention_days');

        if (! config('analytics.enabled') || ! is_int($retention)) {
            $this->components->info('Pruning is disabled.');

            return self::SUCCESS;
        }

        // Whole local days only, so a day is never left half-pruned and under-counted.
        $cutoffDay = DateRange::today()->subDays($retention);
        $oldest = $driver->oldestEventAt();

        if ($oldest === null || $oldest->greaterThanOrEqualTo($cutoffDay->utc())) {
            $this->components->info('No events to prune.');

            return self::SUCCESS;
        }

        // Make sure every day about to lose its raw events is aggregated first.
        $aggregation->aggregate(DateRange::day($oldest), $cutoffDay->subDay());

        $batch = config('analytics.aggregation.prune_batch_size');
        $deleted = $driver->pruneEventsBefore($cutoffDay->utc(), is_int($batch) ? $batch : 1000);

        $this->components->info("Pruned {$deleted} event(s) older than {$cutoffDay->toDateString()}.");

        return self::SUCCESS;
    }
}
