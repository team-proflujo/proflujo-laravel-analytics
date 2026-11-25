<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\DTOs\AnalyticsEventData;

final class RecordAnalyticsEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /** @param  array<string, mixed>  $payload */
    public function __construct(public readonly array $payload) {}

    public function handle(AnalyticsDriver $driver): void
    {
        $driver->insert(AnalyticsEventData::fromArray($this->payload));
    }
}
