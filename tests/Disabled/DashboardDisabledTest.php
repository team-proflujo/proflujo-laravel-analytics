<?php

declare(strict_types=1);

use Proflujo\Analytics\Models\AnalyticsEvent;

it('registers no dashboard routes when disabled', function () {
    $this->get('/analytics')->assertNotFound();
    $this->get('/analytics/charts')->assertNotFound();

    expect(Route::has('analytics.dashboard'))->toBeFalse();
});

it('still tracks events when the dashboard is disabled', function () {
    analytics()->track('a.b');

    expect(AnalyticsEvent::query()->count())->toBe(1);
});
