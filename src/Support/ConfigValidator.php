<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Support;

use InvalidArgumentException;

final class ConfigValidator
{
    public const array FREQUENCIES = ['everyMinute', 'everyFiveMinutes', 'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'daily'];

    /** @throws InvalidArgumentException */
    public static function validate(): void
    {
        foreach ([
            'analytics.collection.max_properties' => 1,
            'analytics.collection.max_depth' => 1,
            'analytics.collection.max_payload_bytes' => 64,
            'analytics.aggregation.prune_batch_size' => 1,
            'analytics.dashboard.per_page' => 1,
            'analytics.dashboard.max_date_range_days' => 1,
            'analytics.dashboard.default_range_days' => 1,
            'analytics.dashboard.max_charts' => 1,
            'analytics.telegram.timeout' => 1,
        ] as $key => $min) {
            $value = config($key);

            if (! is_int($value) || $value < $min) {
                self::fail($key, "must be an integer >= {$min}");
            }
        }

        $retention = config('analytics.aggregation.retention_days');

        if ($retention !== null && (! is_int($retention) || $retention < 1)) {
            self::fail('analytics.aggregation.retention_days', 'must be null or an integer >= 1');
        }

        if (! in_array(config('analytics.aggregation.schedule'), self::FREQUENCIES, true)) {
            self::fail('analytics.aggregation.schedule', 'must be one of: '.implode(', ', self::FREQUENCIES));
        }

        $path = config('analytics.dashboard.path');

        if (! is_string($path) || trim($path, '/') === '' || preg_match('#^[A-Za-z0-9/_-]+$#', trim($path, '/')) !== 1) {
            self::fail('analytics.dashboard.path', 'must be a non-empty URL path segment');
        }

        if (! is_array(config('analytics.dashboard.middleware'))) {
            self::fail('analytics.dashboard.middleware', 'must be an array of middleware');
        }

        if (! is_array(config('analytics.privacy.blocked_keys'))) {
            self::fail('analytics.privacy.blocked_keys', 'must be an array of strings');
        }

        if (config('analytics.dashboard.max_date_range_days') < config('analytics.dashboard.default_range_days')) {
            self::fail('analytics.dashboard.default_range_days', 'must not exceed max_date_range_days');
        }

        if (config('analytics.telegram.enabled')) {
            foreach (['bot_token', 'chat_id'] as $key) {
                $value = config("analytics.telegram.{$key}");

                if ((! is_string($value) && ! is_int($value)) || $value === '') {
                    self::fail("analytics.telegram.{$key}", 'is required when Telegram is enabled');
                }
            }
        }
    }

    private static function fail(string $key, string $reason): never
    {
        throw new InvalidArgumentException("Invalid analytics configuration: [{$key}] {$reason}.");
    }
}
