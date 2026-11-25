# Release Notes

## [Unreleased](https://github.com/proflujo/laravel-analytics/compare/v0.1.0...1.x)

### Added

- Explicit event tracking via `analytics()->track()`, context API, user association and queued recording.
- Event definitions registry (`config/analytics-events.php`) with readable fallbacks.
- Property sanitizer with recursive sensitive-key filtering and payload limits.
- Idempotent, timezone-aware daily aggregation; `analytics:aggregate` and `analytics:prune` with scheduler wiring.
- Read-only dashboard (Chart.js line charts, filters, paginated events) protected by configurable middleware.
- Optional Telegram daily/weekly summaries.

## [v0.1.0](https://github.com/proflujo/laravel-analytics/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
