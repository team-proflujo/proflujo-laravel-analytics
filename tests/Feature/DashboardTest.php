<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/login', fn () => 'login')->name('login');
    $this->travelTo('2026-01-20 12:00:00');
    config(['analytics-events.events' => [
        'student.created' => ['name' => 'Student Created', 'category' => 'Admissions'],
        'payment.completed' => ['name' => 'Payment Completed', 'category' => 'Payments'],
    ]]);
});

function dashboardUser(): GenericUser
{
    return new GenericUser(['id' => 1]);
}

it('redirects guests and protects every route', function () {
    $this->get('/analytics')->assertRedirect('/login');
    $this->getJson('/analytics/events')->assertUnauthorized();
    $this->getJson('/analytics/charts')->assertUnauthorized();
    $this->get('/analytics/assets/dashboard.js')->assertRedirect('/login');
});

it('renders for authenticated users', function () {
    $this->actingAs(dashboardUser())->get('/analytics')
        ->assertOk()
        ->assertSee('Event types')
        ->assertSee('No events were recorded in this period.')
        ->assertSee('No event records match the selected filters.')
        ->assertSee('2025-12-22')
        ->assertSee('2026-01-20');
});

it('honours custom middleware and path', function () {
    $this->actingAs(dashboardUser());
    config(['analytics.dashboard.path' => 'stats']);

    expect(config('analytics.dashboard.middleware'))->toBe(['web', 'auth']);
});

it('serves the bundled assets without a publish step', function () {
    $this->actingAs(dashboardUser());

    $js = $this->get('/analytics/assets/chart.umd.js')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($js->headers->get('Content-Type'))->toContain('javascript');
    $this->get('/analytics/assets/dashboard.css')->assertOk();
    $this->get('/analytics/assets/dashboard.js')->assertOk();
    $this->get('/analytics/assets/composer.json')->assertNotFound();
    $this->get('/analytics/assets/..%2Fconfig')->assertNotFound();
});

it('resolves display metadata and fallback names', function () {
    eventAt('2026-01-19 10:00:00', 'student.created', 'u1');
    eventAt('2026-01-19 10:00:00', 'inventory.stock.adjusted');

    $this->actingAs(dashboardUser())->get('/analytics')
        ->assertSee('Student Created')->assertSee('Admissions')
        ->assertSee('Inventory Stock Adjusted')->assertSee('Uncategorized')
        ->assertSee('inventory.stock.adjusted');
});

it('builds zero-filled chart data from aggregates plus live recent days', function () {
    eventAt('2026-01-10 10:00:00', 'student.created');
    eventAt('2026-01-10 11:00:00', 'student.created');
    eventAt('2026-01-12 10:00:00', 'student.created');
    analytics()->aggregate('2026-01-09', '2026-01-13');
    eventAt('2026-01-20 09:00:00', 'student.created'); // today, not aggregated yet

    $json = $this->actingAs(dashboardUser())->getJson('/analytics/charts?from=2026-01-09&to=2026-01-20')->assertOk()->json();

    expect($json['labels'])->toHaveCount(12)
        ->and($json['total'])->toBe(4)
        ->and($json['event_types'])->toBe(1)
        ->and($json['charts'][0]['event'])->toBe('student.created')
        ->and($json['charts'][0]['name'])->toBe('Student Created')
        ->and($json['charts'][0]['data'])->toBe([0, 2, 0, 1, 0, 0, 0, 0, 0, 0, 0, 1]);
});

it('does not return events without data in the period', function () {
    eventAt('2025-06-01 10:00:00', 'student.created');

    $json = $this->actingAs(dashboardUser())->getJson('/analytics/charts')->json();

    expect($json['charts'])->toBe([])->and($json['total'])->toBe(0);
});

it('uses the application timezone for filters', function () {
    config(['app.timezone' => 'Asia/Kolkata']);
    eventAt('2026-01-17 18:29:00', 'student.created'); // 17th 23:59 IST
    eventAt('2026-01-17 18:30:00', 'student.created'); // 18th 00:00 IST

    $rows = $this->actingAs(dashboardUser())->getJson('/analytics/events?from=2026-01-18&to=2026-01-18')->json('data');

    expect($rows)->toHaveCount(1)->and($rows[0]['occurred_at'])->toBe('2026-01-18 00:00:00 IST');
});

it('filters by event and category', function () {
    eventAt('2026-01-19 10:00:00', 'student.created');
    eventAt('2026-01-19 10:00:00', 'payment.completed');
    eventAt('2026-01-19 10:00:00', 'misc.thing', category: 'Custom');
    eventAt('2026-01-19 10:00:00', 'other.thing');
    analytics()->aggregate('2026-01-19');
    $this->actingAs(dashboardUser());

    $byEvent = $this->getJson('/analytics/events?events[]=payment.completed')->json('data');
    $byCategory = $this->getJson('/analytics/events?category=Admissions')->json('data');
    $byStored = $this->getJson('/analytics/events?category=Custom')->json('data');
    $uncategorized = $this->getJson('/analytics/events?category=Uncategorized')->json('data');
    $charts = $this->getJson('/analytics/charts?events[]=payment.completed')->json();
    $chartsByCategory = $this->getJson('/analytics/charts?category=Custom')->json('charts');

    expect(array_column($byEvent, 'event'))->toBe(['payment.completed'])
        ->and(array_column($byCategory, 'event'))->toBe(['student.created'])
        ->and(array_column($byStored, 'event'))->toBe(['misc.thing'])
        ->and(array_column($uncategorized, 'event'))->toBe(['other.thing'])
        ->and(array_column($charts['charts'], 'event'))->toBe(['payment.completed'])
        ->and($charts['total'])->toBe(1)
        ->and(array_column($chartsByCategory, 'event'))->toBe(['misc.thing']);
});

it('paginates server-side, newest first', function () {
    config(['analytics.dashboard.per_page' => 2]);
    foreach (range(1, 5) as $i) {
        eventAt("2026-01-19 1{$i}:00:00", 'a.b', "u{$i}");
    }
    $this->actingAs(dashboardUser());

    $page1 = $this->getJson('/analytics/events')->json();
    $page3 = $this->getJson('/analytics/events?page=3')->json();

    expect(array_column($page1['data'], 'user_id'))->toBe(['u5', 'u4'])
        ->and($page1['meta'])->toMatchArray(['current_page' => 1, 'last_page' => 3, 'per_page' => 2, 'total' => 5])
        ->and(array_column($page3['data'], 'user_id'))->toBe(['u1']);

    $this->get('/analytics?page=2')->assertOk()->assertSee('Page 2 of 3');
});

it('limits the number of charts and says so', function () {
    config(['analytics.dashboard.max_charts' => 2]);
    foreach (['a.one', 'b.two', 'c.three'] as $event) {
        eventAt('2026-01-19 10:00:00', $event);
    }
    analytics()->aggregate('2026-01-19');

    $this->actingAs(dashboardUser())->get('/analytics')->assertSee('Showing the top 2 of 3 event types');
    expect($this->getJson('/analytics/charts')->json('charts'))->toHaveCount(2);
});

it('escapes event names and properties (XSS)', function () {
    config(['analytics-events.events' => ['a.b' => ['name' => '<script>alert(1)</script>', 'category' => '"><img src=x>']]]);
    eventAt('2026-01-19 10:00:00', 'a.b', '<b>u</b>', ['note' => '</script><script>alert(2)</script>', 'html' => '<img src=x onerror=alert(3)>']);

    $html = $this->actingAs(dashboardUser())->get('/analytics')->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert')
        ->not->toContain('<img src=x')
        ->not->toContain('<b>u</b>')
        ->not->toContain('</script><script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('hides newly blocked keys even in previously stored events', function () {
    eventAt('2026-01-19 10:00:00', 'a.b', properties: ['password' => 'hunter2', 'ok' => 'visible']);

    $this->actingAs(dashboardUser())->get('/analytics')->assertSee('visible')->assertDontSee('hunter2');
});

it('validates query parameters', function (string $query) {
    $this->actingAs(dashboardUser())->getJson('/analytics/charts?'.$query)->assertUnprocessable();
})->with([
    'bad date' => 'from=yesterday',
    'reversed' => 'from=2026-01-10&to=2026-01-01',
    'too long' => 'from=2020-01-01&to=2026-01-01',
    'bad page' => 'page=0',
    'bad event' => 'events[]=Bad Name',
    'events not array' => 'events=x',
    'sql-ish' => 'category[]=1',
]);

it('redirects back with errors on invalid html requests', function () {
    $this->actingAs(dashboardUser())->from('/analytics')->get('/analytics?from=nope')->assertRedirect('/analytics')->assertSessionHasErrors('from');
});

it('never exposes secrets in responses', function () {
    config(['analytics.telegram.bot_token' => 'SECRET-BOT-TOKEN', 'analytics.telegram.chat_id' => '123']);
    eventAt('2026-01-19 10:00:00');

    $user = dashboardUser();
    foreach (['/analytics', '/analytics/charts', '/analytics/events'] as $uri) {
        expect($this->actingAs($user)->get($uri)->getContent())->not->toContain('SECRET-BOT-TOKEN');
    }
});

it('is read-only', function () {
    $this->actingAs(dashboardUser());

    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        $this->{$method}('/analytics')->assertStatus(405);
    }
});
