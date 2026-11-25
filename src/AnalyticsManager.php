<?php

declare(strict_types=1);

namespace Proflujo\Analytics;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Proflujo\Analytics\DTOs\AnalyticsEventData;
use Proflujo\Analytics\Services\AggregationService;
use Proflujo\Analytics\Services\EventCollector;
use Proflujo\Analytics\Services\SummaryService;
use Proflujo\Analytics\Support\DateRange;
use Proflujo\Analytics\Support\InstanceIdentifier;
use Proflujo\Analytics\Support\PropertySanitizer;
use Stringable;

/**
 * Public API behind analytics(). Registered as a *scoped* binding so the context is
 * discarded at the end of every request, queue job and Octane request.
 */
class AnalyticsManager
{
    /** @var array<array-key, mixed> */
    private array $context = [];

    public function __construct(
        private readonly EventCollector $collector,
        private readonly AggregationService $aggregation,
        private readonly SummaryService $summaries,
        private readonly InstanceIdentifier $instance,
    ) {}

    /**
     * Record an application event.
     *
     * @param  array<array-key, mixed>  $properties  Override context properties on key collision.
     * @param  Authenticatable|Model|int|string|null  $user
     *
     * @throws InvalidArgumentException When the event name is malformed.
     */
    public function track(string $event, array $properties = [], mixed $user = null, ?string $category = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        AnalyticsEventData::assertValidName($event);

        $this->collector->collect(new AnalyticsEventData(
            $event,
            PropertySanitizer::fromConfig()->sanitize(array_merge($this->context, $properties)),
            $category !== null && trim($category) !== '' ? mb_substr(trim($category), 0, 128) : null,
            $this->resolveUserId($user),
            CarbonImmutable::now('UTC'),
        ));
    }

    /**
     * Merge properties into the context applied to subsequent track() calls.
     *
     * @param  array<array-key, mixed>  $properties
     */
    public function context(array $properties): self
    {
        $this->context = array_merge($this->context, $properties);

        return $this;
    }

    public function clearContext(): self
    {
        $this->context = [];

        return $this;
    }

    public function enabled(): bool
    {
        return (bool) config('analytics.enabled', true) && (bool) config('analytics.collection.enabled', true);
    }

    /** @return array{id: string, name: string, timezone: string, enabled: bool} */
    public function instance(): array
    {
        return $this->instance->toArray();
    }

    /** @return array{from: string, to: string, total: int, event_types: int, events: array<string, int>} */
    public function summary(DateTimeInterface|string $from, DateTimeInterface|string|null $to = null): array
    {
        return $this->summaries->summary(DateRange::make($from, $to));
    }

    /** Recompute daily aggregates. Returns the number of days processed. */
    public function aggregate(DateTimeInterface|string $from, DateTimeInterface|string|null $to = null): int
    {
        return $this->aggregation->aggregate($from, $to);
    }

    private function resolveUserId(mixed $user): ?string
    {
        if ($user === null && config('analytics.collection.use_authenticated_user')) {
            $user = auth()->user();
        }

        $id = match (true) {
            $user === null => null,
            $user instanceof Authenticatable => $user->getAuthIdentifier(),
            $user instanceof Model => $user->getKey(),
            is_int($user), is_string($user) => $user,
            $user instanceof Stringable => (string) $user,
            default => throw new InvalidArgumentException('The analytics user must be a model, an Authenticatable, or an int/string identifier.'),
        };

        if ($id === null || (is_string($id) && trim($id) === '')) {
            return null;
        }

        return mb_substr((string) $id, 0, 191);
    }
}
