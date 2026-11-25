---
name: analytics-development
description: >
  Record explicit application events with the Proflujo Laravel Analytics package (analytics()->track),
  define event display metadata, and configure its dashboard, aggregation, queueing and Telegram summaries.
license: MIT
metadata:
  author: proflujo
---

# Proflujo Laravel Analytics

Use this skill when a Laravel app needs to record business events or configure `proflujo/laravel-analytics`.

## Primary Goal

- record only the events the developer asks for, through `analytics()->track()`, without adding automatic tracking

## Workflow

### 1. Inspect the app

- confirm PHP ^8.3 and Laravel 12 or 13; check whether `config/analytics.php` is published
- find the code path where the business event happens (controller action, service, job)

### 2. Track the event

```php
analytics()->track('student.created', ['student_id' => $student->id], auth()->user(), 'Admissions');
```

- names are lowercase and dot-separated (`payment.completed`); a malformed name throws `InvalidArgumentException`
- `$user` may be a model, Authenticatable or an id; only the id is stored as a string
- properties override context set with `analytics()->context([...])`; call `analytics()->clearContext()` in long-running non-queue processes
- optional display metadata goes in `config/analytics-events.php` (`name`, `description`, `category`); tracking never requires it

### 3. Operate it

- `php artisan migrate` creates `analytics_events` and `analytics_daily_aggregates`
- schedule: aggregation, pruning and Telegram summaries are registered automatically; the host must run `schedule:run`
- dashboard at `/analytics`, protected by `analytics.dashboard.middleware` (default `['web', 'auth']`); add authorization middleware for admin-only access
- queue mode: `analytics.collection.queue=true` plus a worker for the `analytics` queue; queued is not yet stored
- Telegram: set `ANALYTICS_TELEGRAM_BOT_TOKEN`, `ANALYTICS_TELEGRAM_CHAT_ID` and `analytics.telegram.enabled`

## Rules, References, and Templates

Read before executing:

- the package README for configuration keys, publish tags (`analytics-config`, `analytics-migrations`, `analytics-views`, `analytics-assets`) and troubleshooting

## Examples

- record a payment: `analytics()->track('payment.completed', ['payment_id' => $p->id, 'currency' => 'INR'], $user, 'Payments')`
- backfill a month: `php artisan analytics:aggregate --from=2026-11-01 --to=2026-11-30`

## Anti-patterns

- do not add model observers, middleware or listeners that track everything automatically
- do not pass passwords, tokens, payment data, personal data or whole models as properties
- do not register event definitions in the database; they live in `config/analytics-events.php`
- do not call Telegram or aggregation from request code
- do not document package internals here; keep the skill focused on adoption in Laravel apps
