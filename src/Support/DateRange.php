<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * An inclusive range of calendar days in the application timezone, convertible to
 * half-open [start, end) UTC boundaries (DST-safe: days may be 23 or 25 hours long).
 */
final class DateRange
{
    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {
        if ($to->lessThan($from)) {
            throw new InvalidArgumentException('The end date must not be before the start date.');
        }
    }

    public static function timezone(): string
    {
        /** @var string $tz */
        $tz = config('app.timezone', 'UTC');

        return $tz;
    }

    public static function make(DateTimeInterface|string $from, DateTimeInterface|string|null $to = null): self
    {
        $start = self::day($from);

        return new self($start, $to === null ? $start : self::day($to));
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }

    /** Calendar day (local midnight) for a date string or instant interpreted in the app timezone. */
    public static function day(DateTimeInterface|string $value): CarbonImmutable
    {
        $tz = self::timezone();

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone($tz)->startOfDay();
        }

        return CarbonImmutable::parse($value, $tz)->startOfDay();
    }

    /** First instant of the range, in UTC. */
    public function utcStart(): CarbonImmutable
    {
        return $this->from->utc();
    }

    /** Exclusive end of the range, in UTC. */
    public function utcEnd(): CarbonImmutable
    {
        return $this->to->addDay()->startOfDay()->utc();
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** @return list<string> Y-m-d for every day in the range */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from; $day->lessThanOrEqualTo($this->to); $day = $day->addDay()->startOfDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    public function dayRange(string $date): self
    {
        return self::make($date);
    }
}
