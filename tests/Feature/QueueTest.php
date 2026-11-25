<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Proflujo\Analytics\Jobs\RecordAnalyticsEvent;
use Proflujo\Analytics\Models\AnalyticsEvent;

beforeEach(function () {
    config(['analytics.collection.queue' => true, 'analytics.collection.queue_name' => 'analytics']);
});

it('dispatches a job instead of inserting', function () {
    Bus::fake();

    analytics()->track('a.b', ['x' => 1], 'u1', 'cat');

    Bus::assertDispatched(RecordAnalyticsEvent::class, fn (RecordAnalyticsEvent $job) => $job->payload['event_name'] === 'a.b'
        && $job->queue === 'analytics'
        && $job->payload['properties'] === ['x' => 1]);
    expect(AnalyticsEvent::query()->count())->toBe(0);
});

it('persists the event with its original timestamp when the job runs', function () {
    Queue::fake();
    $this->travelTo('2026-05-01 10:00:00');

    analytics()->track('a.b', ['x' => 1], 'u1', 'cat');

    $this->travelTo('2026-05-01 10:30:00');
    Queue::pushedJobs();
    $job = Queue::pushed(RecordAnalyticsEvent::class)->first();
    app()->call([$job, 'handle']);

    $event = AnalyticsEvent::query()->sole();

    expect($event->occurred_at->toDateTimeString())->toBe('2026-05-01 10:00:00')
        ->and($event->user_id)->toBe('u1')
        ->and($event->category)->toBe('cat');
});

it('does not claim success when the queue is misconfigured', function () {
    config(['queue.default' => 'broken', 'queue.connections.broken' => ['driver' => 'nonexistent']]);
    Log::spy();

    analytics()->track('a.b');

    expect(AnalyticsEvent::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c): bool => $c['queued'] === true && $c['event'] === 'a.b');
});

it('surfaces queue failures when fail_silently is off', function () {
    config(['queue.default' => 'broken', 'queue.connections.broken' => ['driver' => 'nonexistent'], 'analytics.errors.fail_silently' => false]);

    analytics()->track('a.b');
})->throws(InvalidArgumentException::class);

it('bounds retries', function () {
    $job = new RecordAnalyticsEvent([]);

    expect($job->tries)->toBe(3)->and($job->backoff)->toHaveCount(2);
});
