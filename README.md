<div align="center">
    <h1>Proflujo Laravel Analytics</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/proflujo/laravel-analytics"><img src="https://img.shields.io/packagist/v/proflujo/laravel-analytics.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/proflujo/laravel-analytics"><img src="https://img.shields.io/packagist/php-v/proflujo/laravel-analytics.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/proflujo/laravel-analytics"><img src="https://badge.laravel.cloud/badge/proflujo/laravel-analytics?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/proflujo/laravel-analytics/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/proflujo/laravel-analytics/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/proflujo/laravel-analytics"><img src="https://img.shields.io/packagist/dt/proflujo/laravel-analytics.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Lightweight, self-contained, **explicit** application-event analytics for Laravel. You decide which events matter and record them with one call. Data lives in your existing database; there is no analytics server, Redis, Elasticsearch or ClickHouse to run.

```php
analytics()->track('student.created');

analytics()->track('payment.completed', ['amount' => 5000, 'currency' => 'INR']);
```

The package never records anything on its own: no model observers, no query or request logging, no auth or exception tracking.

## Requirements and compatibility

| | Supported |
|---|---|
| PHP | 8.3, 8.4, 8.5 |
| Laravel | 12.x, 13.x |
| Databases | MySQL/MariaDB, PostgreSQL (CI), SQLite (tests/dev) |

Laravel 11 and PHP 8.2 are not supported (the test toolchain, Pest 4/5 and Testbench 10/11, requires PHP 8.3).

## Installation

```bash
composer require proflujo/laravel-analytics
php artisan migrate
```

The service provider is auto-discovered and the migrations are loaded from the package, so nothing needs publishing. Optional publish tags:

```bash
php artisan vendor:publish --tag=analytics-config      # config/analytics.php + config/analytics-events.php
php artisan vendor:publish --tag=analytics-migrations  # also set analytics.database.load_migrations=false
php artisan vendor:publish --tag=analytics-views
php artisan vendor:publish --tag=analytics-assets      # Chart.js + dashboard assets (optional)
```

The dashboard serves its own bundled assets (Chart.js 4.4.7, MIT) through a package route, so no frontend build is needed.

To use a separate connection, set `ANALYTICS_DB_CONNECTION` **before** running the migrations.

## Tracking events

```php
analytics()->track('student.created');

analytics()->track('student.created', [
    'student_id' => $student->id,
    'department' => 'Computer Science',
], auth()->user());

analytics()->track('payment.completed', [
    'payment_id' => $payment->id,
    'amount' => 5000,
    'currency' => 'INR',
], auth()->user(), 'Payments');

// Named arguments work too.
analytics()->track(event: 'report.downloaded', properties: ['report' => 'monthly'], category: 'Reports');
```

The `Proflujo\Analytics\Facades\Analytics` facade (alias `Analytics`) exposes the same API. The `analytics()` helper is only defined if no other function of that name exists.

**Event names** are developer-defined, lowercase and dot-separated (`a-z`, `0-9`, and `.`, `_`, `-` between segments, max 128 characters). A malformed name throws `InvalidArgumentException` - this is always a programming error and is never swallowed. Names do **not** need to be registered anywhere.

**Properties** are a bounded JSON object (see [Privacy](#privacy-and-sanitization)).

**Categories** passed to `track()` are stored with the event; categories from your event definitions take precedence in the dashboard.

### User association

`$user` may be an `Authenticatable`, an Eloquent model, or an int/string/UUID identifier. Only the identifier is stored, as a string (never the model). No user is attached unless you pass one. To default to the logged-in user, set `analytics.collection.use_authenticated_user` to `true`.

### Context

```php
analytics()->context(['module' => 'Admissions']);

analytics()->track('student.created');   // properties include module=Admissions
analytics()->track('student.updated', ['module' => 'Override']); // explicit properties win

analytics()->clearContext();
```

Context is merged (shallowly) into the properties of later events; explicit properties override it. The manager is bound with `scoped()`, so Laravel discards it at the end of every HTTP request, queue job and Octane request. Context never leaks between them. In a plain long-running console process (e.g. a custom daemon that is not a queue worker) call `clearContext()` yourself.

### Other helpers

```php
analytics()->enabled();                       // bool
analytics()->instance();                      // ['id' => ..., 'name' => ..., 'timezone' => ..., 'enabled' => ...]
analytics()->summary('2026-11-01', '2026-11-19'); // totals per event
analytics()->aggregate('2026-11-01', '2026-11-19'); // recompute daily aggregates, returns days processed
```

Dates are local calendar days in `config('app.timezone')`.

## Event definitions and display names

Optional presentation metadata lives in `config/analytics-events.php` (publish it first):

```php
return [
    'events' => [
        'student.created' => [
            'name' => 'Student Created',
            'description' => 'A new student was registered.',
            'category' => 'Admissions',
        ],
    ],
];
```

Definitions are never stored with events, so renaming never touches existing data. Undefined events get a fallback name (`inventory.stock.adjusted` becomes "Inventory Stock Adjusted") and the category "Uncategorized".

## Recording: synchronous vs queued

```php
'collection' => ['queue' => true, 'queue_connection' => null, 'queue_name' => 'analytics'],
```

| | Synchronous (default) | Queued |
|---|---|---|
| Request latency | one indexed insert | one queue push |
| Guarantee on return | event is in the database | event is only *queued* |
| If infrastructure fails | DB error handled by the failure policy | queue error handled by the failure policy; failed jobs retry 3 times with backoff, then land in `failed_jobs` |
| Timestamp | time of `track()` | time of `track()` (preserved through the queue) |

Run a worker for the queue (`php artisan queue:work --queue=analytics`). Never assume a queued event has been stored.

### Failure policy

`analytics.errors.fail_silently` (default `true`) logs persistence/queue failures (event name and exception message only, never properties) and lets your business code continue. Failures are always re-thrown when `APP_DEBUG=true`. Invalid event names always throw.

## Dashboard

Visit `/analytics`. It is read-only: overview, date filter (default last 30 days, capped by `max_date_range_days`), event/category filters, a Chart.js line chart per event type (zero-filled, at most `max_charts` shown with a notice), and a paginated table of raw events with expandable properties. There is no event management UI.

```php
'dashboard' => [
    'enabled' => true,
    'path' => 'analytics',
    'middleware' => ['web', 'auth'],
    'per_page' => 25,
    'max_date_range_days' => 365,
],
```

Data routes: `GET /analytics`, `GET /analytics/events` (JSON), `GET /analytics/charts` (JSON), `GET /analytics/assets/{file}`. All are GET-only, so CSRF does not apply; all inputs are validated.

### Security

The default `['web', 'auth']` blocks unauthenticated visitors. The package does not know your roles, so **restrict it yourself**, for example:

```php
'middleware' => ['web', 'auth', 'can:view-analytics'],
// or: ['web', 'auth', \App\Http\Middleware\EnsureIsAdmin::class]
```

Event names, categories, user IDs and properties are escaped in Blade, and chart data is embedded as inert JSON, so stored values cannot inject markup. Properties are re-filtered against the blocked keys when displayed. Telegram credentials are never rendered or returned.

## Aggregation and scheduling

Raw events are rolled up into `analytics_daily_aggregates` (per event and local calendar day, with distinct users). Aggregation *replaces* each day's row, so it is idempotent.

```bash
php artisan analytics:aggregate                                  # yesterday + today
php artisan analytics:aggregate --date=2026-11-19
php artisan analytics:aggregate --from=2026-11-01 --to=2026-11-19   # backfill
```

The package registers its schedule automatically (`aggregation.schedule`, default `hourly`, with `withoutOverlapping()->onOneServer()`). **Your server must run Laravel's scheduler:**

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The dashboard always reads today and yesterday live from raw events, so recent numbers are accurate even between aggregation runs; older days come from aggregates. After a long scheduler outage, backfill with `--from`.

Day boundaries are computed in the application timezone and converted to half-open UTC ranges, including 23/25-hour DST days. Events are always stored in UTC.

## Data retention

```bash
php artisan analytics:prune
```

Deletes raw events older than `aggregation.retention_days` (default 180; `null` disables) in batches, aligned to whole local days. Days about to lose raw events are aggregated first, and aggregates are never deleted. It is scheduled daily at 02:30.

## Telegram summaries (optional)

```env
ANALYTICS_TELEGRAM_BOT_TOKEN=123456:ABC...
ANALYTICS_TELEGRAM_CHAT_ID=-1001234567890
```

```php
'telegram' => ['enabled' => true, 'daily_summary' => true, 'weekly_summary' => true, 'send_at' => '08:00'],
```

```bash
php artisan analytics:telegram-summary                    # yesterday
php artisan analytics:telegram-summary --period=weekly    # the 7 days ending yesterday
php artisan analytics:telegram-summary --date=2026-11-19
```

Messages use your event display names, are grouped by category, and are split below Telegram's size limit. Requests use the Laravel HTTP client with a timeout (`telegram.timeout`) and a small bounded retry. Failures are logged without the token and never affect tracking (`track()` never calls Telegram). Disabled by default.

## Privacy and sanitization

Every event's properties pass through a sanitizer: keys matching `privacy.blocked_keys` (`password`, `token`, `api_key`, `authorization`, `cookie`, `session`, `card_number`, `cvv`, `otp`, ...) are dropped at any depth, case-insensitively and by whole word segment (`apiKey` and `session_id` are blocked, `tokenizer` is not). Objects and models are never serialized (they become `[unsupported]`), enums and dates are normalized, and depth/count/size are limited by `max_depth`, `max_properties` and `max_payload_bytes` (over-limit data is dropped and `_truncated: true` is added). Rejected values are never logged. The package never collects headers, request bodies, cookies or IPs.

**You are responsible for what you pass in.** Do not send passwords, tokens, payment data, health or government identifiers, free-text user content, or other personal data. Prefer IDs and small enums over names, emails or addresses.

## Troubleshooting

- **No events appear:** check `ANALYTICS_ENABLED`, that you ran `php artisan migrate`, and that `analytics()->enabled()` is true. Queue mode needs a running worker.
- **Charts are empty for older days:** run `php artisan analytics:aggregate --from=...` and make sure the scheduler runs.
- **403/redirect on `/analytics`:** your dashboard middleware rejected you; see Security.
- **`Invalid analytics configuration`:** a config value failed validation at boot; the message names the key.
- **Event not tracked, exception thrown:** the event name is malformed.

## Upgrading

This is the first release. Breaking changes will be listed in the [CHANGELOG](CHANGELOG.md).

## Testing

```bash
composer test            # PHPStan, Pint, 100% type coverage, Pest
```

Run the suite against MySQL or PostgreSQL by setting `ANALYTICS_TEST_DB` (`mysql` or `pgsql`) and, as needed, `ANALYTICS_TEST_DB_HOST`, `_PORT`, `_DATABASE`, `_USERNAME`, `_PASSWORD`, then `vendor/bin/pest`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Proflujo Laravel Analytics! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [proflujo](https://github.com/proflujo)
- [All Contributors](../../contributors)

## License

Proflujo Laravel Analytics is open-sourced software licensed under the [MIT license](LICENSE.md).
