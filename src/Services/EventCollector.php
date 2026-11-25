<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\DTOs\AnalyticsEventData;
use Proflujo\Analytics\Jobs\RecordAnalyticsEvent;

/**
 * Persists (or queues) an already validated and sanitized event, applying the failure policy.
 */
final class EventCollector
{
    public function __construct(private readonly AnalyticsDriver $driver) {}

    public function collect(AnalyticsEventData $event): void
    {
        try {
            if ($this->queued()) {
                $this->dispatch($event);

                return;
            }

            $this->driver->insert($event);
        } catch (Exception $e) {
            if (! $this->failSilently()) {
                throw $e;
            }

            // Only the event name and exception class/message: never properties.
            Log::warning('Analytics event could not be recorded.', [
                'event' => $event->eventName,
                'queued' => $this->queued(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function dispatch(AnalyticsEventData $event): void
    {
        $job = new RecordAnalyticsEvent($event->toArray());

        $connection = config('analytics.collection.queue_connection');
        $queue = config('analytics.collection.queue_name');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        dispatch($job);
    }

    private function queued(): bool
    {
        return (bool) config('analytics.collection.queue', false);
    }

    private function failSilently(): bool
    {
        return (bool) config('analytics.errors.fail_silently', true) && ! config('app.debug');
    }
}
