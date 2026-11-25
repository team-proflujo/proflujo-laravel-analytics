<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Services;

use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use InvalidArgumentException;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\Support\DateRange;

final class AggregationService
{
    public function __construct(private readonly AnalyticsDriver $driver) {}

    /**
     * Recompute daily aggregates for every local calendar day in the range. Each day is
     * replaced (never incremented), so running this repeatedly is idempotent.
     *
     * @return int Number of days processed.
     */
    public function aggregate(DateTimeInterface|string $from, DateTimeInterface|string|null $to = null): int
    {
        try {
            $range = DateRange::make($from, $to);
        } catch (InvalidFormatException $e) {
            throw new InvalidArgumentException('Invalid date supplied for aggregation.', 0, $e);
        }

        foreach ($range->dates() as $date) {
            $this->driver->storeAggregates($date, $this->driver->countDay($range->dayRange($date)));
        }

        return $range->days();
    }
}
