<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Proflujo\Analytics\AnalyticsManager;
use Proflujo\Analytics\AnalyticsServiceProvider;
use Proflujo\Analytics\Facades\Analytics;
use Proflujo\Analytics\Support\InstanceIdentifier;

it('registers the provider and is auto-discoverable', function () {
    expect(app()->getProviders(AnalyticsServiceProvider::class))->not->toBeEmpty();

    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe([AnalyticsServiceProvider::class])
        ->and($composer['extra']['laravel']['aliases']['Analytics'])->toBe(Analytics::class);
});

it('resolves the helper and facade to the same scoped manager', function () {
    expect(analytics())->toBeInstanceOf(AnalyticsManager::class)
        ->and(analytics())->toBe(app(AnalyticsManager::class))
        ->and(Analytics::enabled())->toBeTrue();
});

it('merges both config files', function () {
    expect(config('analytics.dashboard.path'))->toBe('analytics')
        ->and(config('analytics.collection.queue'))->toBeFalse()
        ->and(config('analytics-events.events'))->toBeArray();
});

it('exposes publish tags', function () {
    foreach (['analytics-config', 'analytics-migrations', 'analytics-views', 'analytics-assets'] as $tag) {
        expect(ServiceProvider::pathsToPublish(AnalyticsServiceProvider::class, $tag))->not->toBeEmpty();
    }

    expect(array_values(ServiceProvider::pathsToPublish(AnalyticsServiceProvider::class, 'analytics-config')))
        ->toHaveCount(2);
});

it('runs the migrations', function () {
    expect(Schema::hasTable('analytics_events'))->toBeTrue()
        ->and(Schema::hasTable('analytics_daily_aggregates'))->toBeTrue()
        ->and(Schema::hasColumns('analytics_events', ['event_name', 'category', 'user_id', 'properties', 'occurred_at', 'created_at']))->toBeTrue();
});

it('registers the artisan commands', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('analytics:aggregate', 'analytics:prune', 'analytics:telegram-summary');
});

it('schedules aggregation and pruning', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");

    expect($events)->toContain('analytics:aggregate')->toContain('analytics:prune')->not->toContain('telegram');
});

it('uses the configured instance id and generates a persistent one otherwise', function () {
    expect(analytics()->instance())->toMatchArray(['id' => 'test-instance', 'enabled' => true]);

    config(['analytics.instance.id' => null]);
    $path = storage_path('app/analytics/instance-id');
    @unlink($path);

    $first = (new InstanceIdentifier)->id();
    $second = (new InstanceIdentifier)->id();

    expect($first)->toBe($second)->and(strlen($first))->toBe(36);

    @unlink($path);
});

it('rejects invalid configuration', function () {
    config(['analytics.collection.max_depth' => 0]);

    (new AnalyticsServiceProvider(app()))->boot();
})->throws(InvalidArgumentException::class, 'analytics.collection.max_depth');
