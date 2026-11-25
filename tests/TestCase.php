<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Proflujo\Analytics\AnalyticsServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [AnalyticsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.debug', false);
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('analytics.instance.id', 'test-instance');

        $driver = (string) env('ANALYTICS_TEST_DB', 'sqlite');

        if ($driver === 'mysql' || $driver === 'pgsql') {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => $driver,
                'host' => env('ANALYTICS_TEST_DB_HOST', '127.0.0.1'),
                'port' => env('ANALYTICS_TEST_DB_PORT', $driver === 'mysql' ? 3306 : 5432),
                'database' => env('ANALYTICS_TEST_DB_DATABASE', 'analytics_test'),
                'username' => env('ANALYTICS_TEST_DB_USERNAME', 'root'),
                'password' => env('ANALYTICS_TEST_DB_PASSWORD', ''),
                'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
                'prefix' => '',
            ]);
        } else {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
