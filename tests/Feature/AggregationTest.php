<?php

declare(strict_types=1);

use Proflujo\Analytics\Models\AnalyticsDailyAggregate;
use Proflujo\Analytics\Models\AnalyticsEvent;

function aggregates(): array
{
    return AnalyticsDailyAggregate::query()->orderBy('event_name')->orderBy('date')->get()
        ->map(fn ($a) => [$a->event_name, substr((string) $a->date, 0, 10), $a->event_count, $a->unique_users])->all();
}

it('aggregates counts and unique users per event and day', function () {
    eventAt('2026-01-10 09:00:00', 'student.created', 'u1');
    eventAt('2026-01-10 10:00:00', 'student.created', 'u1');
    eventAt('2026-01-10 11:00:00', 'student.created', 'u2');
    eventAt('2026-01-10 12:00:00', 'student.created');
    eventAt('2026-01-10 13:00:00', 'report.downloaded');
    eventAt('2026-01-11 01:00:00', 'student.created', 'u3');

    expect(analytics()->aggregate('2026-01-10', '2026-01-11'))->toBe(2)
        ->and(aggregates())->toBe([
            ['report.downloaded', '2026-01-10', 1, null],
            ['student.created', '2026-01-10', 4, 2],
            ['student.created', '2026-01-11', 1, 1],
        ]);
});

it('is idempotent', function () {
    eventAt('2026-01-10 09:00:00', 'a.b', 'u1');
    eventAt('2026-01-10 09:05:00', 'a.b', 'u2');

    analytics()->aggregate('2026-01-10');
    analytics()->aggregate('2026-01-10');
    $this->artisan('analytics:aggregate', ['--date' => '2026-01-10'])->assertSuccessful();

    expect(aggregates())->toBe([['a.b', '2026-01-10', 2, 2]]);
});

it('picks up late events when re-run', function () {
    eventAt('2026-01-10 09:00:00', 'a.b');
    analytics()->aggregate('2026-01-10');
    eventAt('2026-01-10 20:00:00', 'a.b');
    analytics()->aggregate('2026-01-10');

    expect(aggregates())->toBe([['a.b', '2026-01-10', 2, null]]);
});

it('uses local calendar days converted to UTC boundaries', function () {
    config(['app.timezone' => 'Asia/Kolkata']);

    eventAt('2026-11-18 18:29:59', 'a.b'); // 18 Nov 23:59:59 IST
    eventAt('2026-11-18 18:30:00', 'a.b'); // 19 Nov 00:00:00 IST
    eventAt('2026-11-19 18:29:59', 'a.b'); // 19 Nov 23:59:59 IST
    eventAt('2026-11-19 18:30:00', 'a.b'); // 20 Nov 00:00:00 IST

    analytics()->aggregate('2026-11-18', '2026-11-20');

    expect(aggregates())->toBe([
        ['a.b', '2026-11-18', 1, null],
        ['a.b', '2026-11-19', 2, null],
        ['a.b', '2026-11-20', 1, null],
    ]);
});

it('handles 23 and 25 hour days around daylight saving changes', function () {
    config(['app.timezone' => 'America/New_York']);

    // 2026-03-08 spring forward: local day is 05:00Z -> 04:00Z next day.
    eventAt('2026-03-08 04:59:59', 'a.b'); // 7th, 23:59:59 EST
    eventAt('2026-03-08 05:00:00', 'a.b'); // 8th, 00:00 EST
    eventAt('2026-03-09 03:59:59', 'a.b'); // 8th, 23:59:59 EDT
    eventAt('2026-03-09 04:00:00', 'a.b'); // 9th

    analytics()->aggregate('2026-03-07', '2026-03-09');

    expect(aggregates())->toBe([
        ['a.b', '2026-03-07', 1, null],
        ['a.b', '2026-03-08', 2, null],
        ['a.b', '2026-03-09', 1, null],
    ]);
});

it('rejects malformed dates in the command', function () {
    $this->artisan('analytics:aggregate', ['--date' => 'not-a-date'])->assertExitCode(2);
    $this->artisan('analytics:aggregate', ['--date' => '2026-01-01', '--from' => '2026-01-01'])->assertExitCode(2);
});

it('defaults to yesterday and today', function () {
    $this->travelTo('2026-02-10 12:00:00');
    eventAt('2026-02-09 12:00:00', 'a.b');
    eventAt('2026-02-10 08:00:00', 'a.b');
    eventAt('2026-02-08 08:00:00', 'a.b');

    $this->artisan('analytics:aggregate')->assertSuccessful();

    expect(aggregates())->toBe([['a.b', '2026-02-09', 1, null], ['a.b', '2026-02-10', 1, null]]);
});

it('does nothing when disabled', function () {
    config(['analytics.enabled' => false]);
    eventAt('2026-01-10 09:00:00');

    $this->artisan('analytics:aggregate', ['--date' => '2026-01-10'])->assertSuccessful();

    expect(aggregates())->toBe([]);
});

describe('prune', function () {
    it('removes old raw events but keeps aggregates', function () {
        $this->travelTo('2026-06-30 12:00:00');
        config(['analytics.aggregation.retention_days' => 30]);

        eventAt('2026-04-01 10:00:00', 'a.b', 'u1');
        eventAt('2026-04-01 11:00:00', 'a.b', 'u2');
        eventAt('2026-06-20 10:00:00', 'a.b');
        eventAt('2026-06-29 10:00:00', 'a.b');

        $this->artisan('analytics:prune')->assertSuccessful();

        expect(AnalyticsEvent::query()->pluck('occurred_at')->map->toDateString()->all())->toBe(['2026-06-20', '2026-06-29'])
            ->and(aggregates())->toContain(['a.b', '2026-04-01', 2, 2])
            ->and(aggregates())->toHaveCount(1);
    });

    it('prunes in batches', function () {
        $this->travelTo('2026-06-30 12:00:00');
        config(['analytics.aggregation.retention_days' => 30, 'analytics.aggregation.prune_batch_size' => 2]);

        foreach (range(1, 5) as $i) {
            eventAt("2026-03-0{$i} 10:00:00");
        }

        $this->artisan('analytics:prune')->expectsOutputToContain('Pruned 5')->assertSuccessful();
        expect(AnalyticsEvent::query()->count())->toBe(0)->and(AnalyticsEvent::query()->count())->toBe(0);
    });

    it('can be disabled', function () {
        config(['analytics.aggregation.retention_days' => null]);
        eventAt('2020-01-01 00:00:00');

        $this->artisan('analytics:prune')->expectsOutputToContain('disabled')->assertSuccessful();

        expect(AnalyticsEvent::query()->count())->toBe(1);
    });
});

describe('summary', function () {
    it('returns zero-safe totals per event for a range', function () {
        eventAt('2026-01-10 09:00:00', 'a.b');
        eventAt('2026-01-12 09:00:00', 'a.b');
        eventAt('2026-01-12 09:00:00', 'c.d');
        analytics()->aggregate('2026-01-10', '2026-01-12');

        expect(analytics()->summary('2026-01-10', '2026-01-12'))->toBe([
            'from' => '2026-01-10', 'to' => '2026-01-12', 'total' => 3, 'event_types' => 2, 'events' => ['a.b' => 2, 'c.d' => 1],
        ])->and(analytics()->summary('2025-01-01', '2025-01-05')['total'])->toBe(0);
    });
});
