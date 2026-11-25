<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Proflujo\Analytics\Models\AnalyticsEvent;
use Proflujo\Analytics\Tests\Support\DashboardDisabledTestCase;
use Proflujo\Analytics\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'ArchTest.php');
uses(DashboardDisabledTestCase::class)->in('Disabled');

/**
 * Insert a raw event at an explicit UTC instant.
 *
 * @param  array<string, mixed>  $properties
 */
function eventAt(string $utc, string $name = 'student.created', ?string $user = null, array $properties = [], ?string $category = null): AnalyticsEvent
{
    return AnalyticsEvent::query()->create([
        'event_name' => $name,
        'category' => $category,
        'user_id' => $user,
        'properties' => $properties,
        'occurred_at' => CarbonImmutable::parse($utc, 'UTC')->format('Y-m-d H:i:s.u'),
        'created_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u'),
    ]);
}
