<?php

declare(strict_types=1);

use Proflujo\Analytics\Support\DateRange;

it('converts local days to half-open UTC boundaries', function () {
    config(['app.timezone' => 'Asia/Kolkata']);

    $range = DateRange::make('2026-11-19');

    expect($range->utcStart()->toIso8601String())->toBe('2026-11-18T18:30:00+00:00')
        ->and($range->utcEnd()->toIso8601String())->toBe('2026-11-19T18:30:00+00:00');
});

it('handles daylight saving transitions', function () {
    config(['app.timezone' => 'America/New_York']);

    $spring = DateRange::make('2026-03-08'); // 23 hours
    $fall = DateRange::make('2026-11-01');   // 25 hours

    expect($spring->utcStart()->diffInHours($spring->utcEnd()))->toBe(23.0)
        ->and($fall->utcStart()->diffInHours($fall->utcEnd()))->toBe(25.0)
        ->and(DateRange::make('2026-03-07', '2026-03-10')->dates())->toBe(['2026-03-07', '2026-03-08', '2026-03-09', '2026-03-10']);
});

it('rejects reversed ranges', function () {
    DateRange::make('2026-02-02', '2026-02-01');
})->throws(InvalidArgumentException::class);
