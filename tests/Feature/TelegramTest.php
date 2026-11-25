<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Proflujo\Analytics\AnalyticsServiceProvider;
use Proflujo\Analytics\Models\AnalyticsEvent;
use Proflujo\Analytics\Services\TelegramService;
use Proflujo\Analytics\Support\DateRange;

beforeEach(function () {
    $this->travelTo('2026-11-20 09:00:00');
    config([
        'analytics.telegram.enabled' => true,
        'analytics.telegram.bot_token' => '123456:SECRET-TOKEN',
        'analytics.telegram.chat_id' => '-100200',
        'analytics.instance.name' => 'Example Application',
        'analytics-events.events' => [
            'student.created' => ['name' => 'Student Created', 'category' => 'Admissions'],
            'student.updated' => ['name' => 'Student Updated', 'category' => 'Admissions'],
            'payment.completed' => ['name' => 'Payment Completed', 'category' => 'Payments'],
        ],
    ]);
});

it('sends nothing when disabled', function () {
    Http::fake();
    config(['analytics.telegram.enabled' => false]);

    $this->artisan('analytics:telegram-summary')->expectsOutputToContain('disabled')->assertSuccessful();

    Http::assertNothingSent();
});

it('does not send anything while tracking events', function () {
    Http::fake();

    analytics()->track('student.created');

    Http::assertNothingSent();
});

it('builds a grouped daily summary with display names', function () {
    eventAt('2026-11-19 10:00:00', 'student.created');
    eventAt('2026-11-19 11:00:00', 'student.created');
    eventAt('2026-11-19 12:00:00', 'student.updated');
    eventAt('2026-11-19 13:00:00', 'payment.completed');
    eventAt('2026-11-19 14:00:00', 'inventory.stock.adjusted');
    eventAt('2026-11-18 14:00:00', 'student.created'); // outside the period

    $message = app(TelegramService::class)->buildMessage('daily', DateRange::make('2026-11-19'));

    expect($message)->toBe(implode("\n", [
        'Proflujo Analytics - Example Application',
        '',
        'Daily Summary',
        'Date: 19 November 2026',
        '',
        'Total Events: 5',
        '',
        'Admissions',
        '- Student Created: 2',
        '- Student Updated: 1',
        '',
        'Payments',
        '- Payment Completed: 1',
        '',
        'Uncategorized',
        '- Inventory Stock Adjusted: 1',
    ]));
});

it('sends the daily summary for yesterday and the weekly one for the previous 7 days', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    eventAt('2026-11-19 10:00:00', 'student.created');
    eventAt('2026-11-13 10:00:00', 'student.created');
    eventAt('2026-11-12 10:00:00', 'student.created'); // before the weekly period

    $this->artisan('analytics:telegram-summary')->assertSuccessful();
    $this->artisan('analytics:telegram-summary', ['--period' => 'weekly'])->assertSuccessful();

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data()['text'])->all();

    expect($sent[0])->toContain('Daily Summary')->toContain('Date: 19 November 2026')->toContain('Total Events: 1')
        ->and($sent[1])->toContain('Weekly Summary')->toContain('Period: 13 November 2026 - 19 November 2026')->toContain('Total Events: 2');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bot123456:SECRET-TOKEN/sendMessage'
        && $request['chat_id'] === '-100200');
});

it('uses the application timezone for report periods', function () {
    config(['app.timezone' => 'Asia/Kolkata']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    eventAt('2026-11-18 18:30:00', 'student.created'); // 19 Nov 00:00 IST
    eventAt('2026-11-18 18:29:00', 'student.created'); // 18 Nov IST

    $this->artisan('analytics:telegram-summary', ['--date' => '2026-11-19'])->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request['text'], 'Total Events: 1'));
});

it('splits long messages on line boundaries', function () {
    $lines = implode("\n", array_map(fn (int $i) => str_repeat('x', 100).$i, range(1, 120)));
    $chunks = app(TelegramService::class)->chunk($lines);

    expect(count($chunks))->toBeGreaterThan(2)
        ->and(max(array_map('mb_strlen', $chunks)))->toBeLessThanOrEqual(4000)
        ->and(implode("\n", $chunks))->toBe($lines);
});

it('sends every chunk of a long report', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    foreach (range(1, 150) as $i) {
        eventAt('2026-11-19 10:00:00', 'bulk.event.number'.str_repeat('x', 60).$i);
    }

    $this->artisan('analytics:telegram-summary')->assertSuccessful();

    expect(Http::recorded()->count())->toBeGreaterThan(1)
        ->and(Http::recorded()->every(fn ($pair) => mb_strlen($pair[0]->data()['text']) <= 4096))->toBeTrue();
});

it('handles API failures without leaking the token', function () {
    Log::spy();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized 123456:SECRET-TOKEN'], 401)]);

    $this->artisan('analytics:telegram-summary')->expectsOutputToContain('could not be sent')->assertFailed();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c): bool => ! str_contains(json_encode($c), 'SECRET-TOKEN') && $c['status'] === 401);
});

it('handles network exceptions without leaking the token', function () {
    Log::spy();
    Http::fake(fn () => throw new ConnectionException('cURL error for https://api.telegram.org/bot123456:SECRET-TOKEN/sendMessage'));

    expect(app(TelegramService::class)->sendSummary('daily'))->toBeFalse();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c): bool => ! str_contains(json_encode($c), 'SECRET-TOKEN'));
});

it('does not affect event collection when Telegram is failing', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    analytics()->track('student.created');

    expect(AnalyticsEvent::query()->count())->toBe(1);
});

it('rejects unknown periods', function () {
    $this->artisan('analytics:telegram-summary', ['--period' => 'monthly'])->assertExitCode(2);
});

it('requires credentials when enabled', function () {
    config(['analytics.telegram.bot_token' => null]);

    (new AnalyticsServiceProvider(app()))->boot();
})->throws(InvalidArgumentException::class, 'bot_token');

it('schedules summaries only when enabled', function () {
    config(['analytics.telegram.daily_summary' => true, 'analytics.telegram.weekly_summary' => true]);
    $schedule = new Schedule;
    $method = new ReflectionMethod(AnalyticsServiceProvider::class, 'schedule');
    $method->invoke(new AnalyticsServiceProvider(app()), $schedule);

    $commands = collect($schedule->events())->map(fn ($e) => $e->command)->implode("\n");

    expect($commands)->toContain('--period=daily')->toContain('--period=weekly');
});
