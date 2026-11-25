<?php

declare(strict_types=1);

return [

    /*
    | Master switch. When false, nothing is recorded, scheduled or reported.
    */
    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    | Installation identity. When no id is configured, a random UUID is generated
    | once and persisted in storage/app/analytics/instance-id.
    */
    'instance' => [
        'id' => env('ANALYTICS_INSTANCE_ID'),
        'name' => env('ANALYTICS_INSTANCE_NAME', env('APP_NAME')),
    ],

    /*
    | Database connection for analytics tables. null = the default connection.
    | Set this before running the migrations.
    */
    'database' => [
        'connection' => env('ANALYTICS_DB_CONNECTION'),
        // Set to false to publish the migrations (analytics-migrations) and manage them yourself.
        'load_migrations' => true,
    ],

    'collection' => [
        'enabled' => true,
        // false = insert synchronously; true = dispatch a queued job (see README trade-offs).
        'queue' => false,
        'queue_connection' => null,
        'queue_name' => 'analytics',
        // Attribute events to the authenticated user when none is passed to track().
        'use_authenticated_user' => false,
        'max_properties' => 50,
        'max_payload_bytes' => 8192,
        'max_depth' => 5,
    ],

    'privacy' => [
        // Matched case-insensitively against whole key segments at any nesting depth.
        'blocked_keys' => [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'api_key',
            'authorization',
            'cookie',
            'session',
            'credit_card',
            'card_number',
            'cvv',
            'otp',
        ],
    ],

    'errors' => [
        // Swallow (and log) persistence failures so analytics never breaks business code.
        // Failures are always re-thrown when app.debug is true.
        'fail_silently' => true,
    ],

    'aggregation' => [
        'enabled' => true,
        // Any scheduler frequency method without arguments: everyMinute, hourly, daily, ...
        'schedule' => 'hourly',
        // Raw events older than this are removed by analytics:prune. null disables pruning.
        'retention_days' => 180,
        'prune_batch_size' => 1000,
    ],

    'dashboard' => [
        'enabled' => true,
        'path' => 'analytics',
        'middleware' => ['web', 'auth'],
        'per_page' => 25,
        'max_date_range_days' => 365,
        'default_range_days' => 30,
        'max_charts' => 12,
    ],

    'telegram' => [
        'enabled' => false,
        'bot_token' => env('ANALYTICS_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('ANALYTICS_TELEGRAM_CHAT_ID'),
        'timeout' => 10,
        'daily_summary' => false,
        'weekly_summary' => false,
        // Local time (HH:MM) at which scheduled summaries are sent.
        'send_at' => '08:00',
    ],

];
