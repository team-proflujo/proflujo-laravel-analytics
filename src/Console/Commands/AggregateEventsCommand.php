<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Proflujo\Analytics\Services\AggregationService;
use Proflujo\Analytics\Support\DateRange;

final class AggregateEventsCommand extends Command
{
    protected $signature = 'analytics:aggregate
        {--date= : Aggregate a single local date (Y-m-d)}
        {--from= : First local date of a range (Y-m-d)}
        {--to= : Last local date of a range (Y-m-d, defaults to today)}';

    protected $description = 'Aggregate raw analytics events into daily counts (idempotent)';

    public function handle(AggregationService $aggregation): int
    {
        if (! config('analytics.enabled')) {
            $this->components->warn('Analytics is disabled.');

            return self::SUCCESS;
        }

        try {
            [$from, $to] = $this->resolveRange();
            $days = $aggregation->aggregate($from, $to);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        $this->components->info("Aggregated {$days} day(s): {$from} to {$to}.");

        return self::SUCCESS;
    }

    /** @return array{string, string} */
    private function resolveRange(): array
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date !== null && ($from !== null || $to !== null)) {
            throw new InvalidArgumentException('Use either --date or --from/--to, not both.');
        }

        if ($date !== null) {
            $day = DateRange::day($this->stringOption($date));

            return [$day->toDateString(), $day->toDateString()];
        }

        $today = DateRange::today();
        $end = $to !== null ? DateRange::day($this->stringOption($to)) : $today;

        // Default: yesterday + today, so the day boundary is always closed out.
        $start = $from !== null ? DateRange::day($this->stringOption($from)) : $today->subDay();

        return [$start->toDateString(), $end->toDateString()];
    }

    private function stringOption(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
