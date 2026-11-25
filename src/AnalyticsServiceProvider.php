<?php

declare(strict_types=1);

namespace Proflujo\Analytics;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Proflujo\Analytics\Console\Commands\AggregateEventsCommand;
use Proflujo\Analytics\Console\Commands\PruneEventsCommand;
use Proflujo\Analytics\Console\Commands\SendTelegramSummaryCommand;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\Drivers\DatabaseDriver;
use Proflujo\Analytics\Services\AggregationService;
use Proflujo\Analytics\Services\DashboardService;
use Proflujo\Analytics\Services\EventCollector;
use Proflujo\Analytics\Services\SummaryService;
use Proflujo\Analytics\Services\TelegramService;
use Proflujo\Analytics\Support\ConfigValidator;
use Proflujo\Analytics\Support\EventDefinitionRegistry;
use Proflujo\Analytics\Support\InstanceIdentifier;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/analytics.php', 'analytics');
        $this->mergeConfigFrom(__DIR__.'/../config/analytics-events.php', 'analytics-events');

        $this->app->singleton(EventDefinitionRegistry::class);
        $this->app->singleton(InstanceIdentifier::class);
        $this->app->singleton(AnalyticsDriver::class, DatabaseDriver::class);
        $this->app->singleton(EventCollector::class);
        $this->app->singleton(AggregationService::class);
        $this->app->singleton(SummaryService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(TelegramService::class);

        // Scoped: the context is reset for every request, queue job and Octane request.
        $this->app->scoped(AnalyticsManager::class);
    }

    public function boot(): void
    {
        ConfigValidator::validate();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'analytics');

        if (config('analytics.database.load_migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if (config('analytics.dashboard.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/analytics.php' => config_path('analytics.php'),
            __DIR__.'/../config/analytics-events.php' => config_path('analytics-events.php'),
        ], ['analytics', 'analytics-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['analytics', 'analytics-migrations']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/analytics'),
        ], ['analytics', 'analytics-views']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/analytics'),
        ], ['analytics', 'analytics-assets']);

        $this->commands([
            AggregateEventsCommand::class,
            PruneEventsCommand::class,
            SendTelegramSummaryCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, $this->schedule(...));
    }

    private function schedule(Schedule $schedule): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        if (config('analytics.aggregation.enabled')) {
            /** @var string $frequency validated by ConfigValidator */
            $frequency = config('analytics.aggregation.schedule');

            $schedule->command('analytics:aggregate')->{$frequency}()->withoutOverlapping()->onOneServer()->runInBackground();

            if (config('analytics.aggregation.retention_days') !== null) {
                $schedule->command('analytics:prune')->dailyAt('02:30')->withoutOverlapping()->onOneServer()->runInBackground();
            }
        }

        if (config('analytics.telegram.enabled')) {
            $at = is_string(config('analytics.telegram.send_at')) ? (string) config('analytics.telegram.send_at') : '08:00';

            if (config('analytics.telegram.daily_summary')) {
                $schedule->command('analytics:telegram-summary --period=daily')->dailyAt($at)->onOneServer();
            }

            if (config('analytics.telegram.weekly_summary')) {
                $schedule->command('analytics:telegram-summary --period=weekly')->weeklyOn(1, $at)->onOneServer();
            }
        }
    }
}
