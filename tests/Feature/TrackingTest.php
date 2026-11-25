<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Proflujo\Analytics\Contracts\AnalyticsDriver;
use Proflujo\Analytics\DTOs\AnalyticsEventData;
use Proflujo\Analytics\Models\AnalyticsEvent;
use Proflujo\Analytics\Services\EventCollector;
use Proflujo\Analytics\Support\DateRange;

it('tracks a basic event', function () {
    analytics()->track('student.created');

    $event = AnalyticsEvent::query()->sole();

    expect($event->event_name)->toBe('student.created')
        ->and($event->properties)->toBe([])
        ->and($event->user_id)->toBeNull()
        ->and($event->category)->toBeNull()
        ->and($event->occurred_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('tracks properties, category and an explicit user', function () {
    analytics()->track(event: 'payment.completed', properties: ['amount' => 5000, 'currency' => 'INR'], user: 42, category: 'business');

    $event = AnalyticsEvent::query()->sole();

    expect($event->properties)->toBe(['amount' => 5000, 'currency' => 'INR'])
        ->and($event->category)->toBe('business')
        ->and($event->user_id)->toBe('42');
});

it('stores uuid, string, authenticatable and model identifiers as strings', function () {
    $uuid = '0198d4b0-aaaa-7bbb-8ccc-123456789abc';
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->forceFill(['id' => 7]);

    analytics()->track('a.b', user: $uuid);
    analytics()->track('a.b', user: new GenericUser(['id' => 'abc-1']));
    analytics()->track('a.b', user: $model);
    analytics()->track('a.b', user: 'ext_99');

    expect(AnalyticsEvent::query()->orderBy('id')->pluck('user_id')->all())->toBe([$uuid, 'abc-1', '7', 'ext_99']);
});

it('rejects unsupported user values', function () {
    analytics()->track('a.b', user: ['id' => 1]);
})->throws(InvalidArgumentException::class);

it('uses the authenticated user only when enabled', function () {
    $this->actingAs(new GenericUser(['id' => 5]));

    analytics()->track('a.b');
    expect(AnalyticsEvent::query()->sole()->user_id)->toBeNull();

    config(['analytics.collection.use_authenticated_user' => true]);
    analytics()->track('a.c');
    expect(AnalyticsEvent::query()->where('event_name', 'a.c')->sole()->user_id)->toBe('5');
});

it('tracks events that have no definition', function () {
    analytics()->track('totally.undefined.event');

    expect(AnalyticsEvent::query()->count())->toBe(1);
});

it('validates event names', function (string $name) {
    expect(fn () => analytics()->track($name))->toThrow(InvalidArgumentException::class);
    expect(AnalyticsEvent::query()->count())->toBe(0);
})->with(['', 'Student.Created', 'student created', 'student..created', '.student', 'student.', str_repeat('a', 129), "student\n.created", 'student/created']);

it('accepts conventional names', function (string $name) {
    analytics()->track($name);

    expect(AnalyticsEvent::query()->count())->toBe(1);
})->with(['student.created', 'report.downloaded', 'a', 'inventory.stock_level.adjusted', 'v2.upgrade-clicked']);

it('writes nothing when disabled', function () {
    config(['analytics.enabled' => false]);

    analytics()->track('student.created');
    expect(analytics()->enabled())->toBeFalse()->and(AnalyticsEvent::query()->count())->toBe(0);

    config(['analytics.enabled' => true, 'analytics.collection.enabled' => false]);
    analytics()->track('student.created');

    expect(AnalyticsEvent::query()->count())->toBe(0);
});

it('sanitizes properties before storing and never logs them', function () {
    Log::spy();

    analytics()->track('a.b', ['password' => 'hunter2', 'nested' => ['token' => 'abc', 'ok' => 1], 'model' => new stdClass]);

    expect(AnalyticsEvent::query()->sole()->properties)->toBe(['nested' => ['ok' => 1], 'model' => '[unsupported]']);
    Log::shouldNotHaveReceived('warning');
});

describe('context', function () {
    it('merges context into subsequent events and clears it', function () {
        analytics()->context(['module' => 'Admissions', 'department' => 'CS']);
        analytics()->track('student.created');
        analytics()->clearContext();
        analytics()->track('student.updated');

        $events = AnalyticsEvent::query()->orderBy('id')->get();

        expect($events[0]->properties)->toBe(['module' => 'Admissions', 'department' => 'CS'])
            ->and($events[1]->properties)->toBe([]);
    });

    it('lets explicit properties override context', function () {
        analytics()->context(['module' => 'Admissions', 'department' => 'CS']);
        analytics()->track('student.created', ['department' => 'Maths']);

        expect(AnalyticsEvent::query()->sole()->properties)->toBe(['module' => 'Admissions', 'department' => 'Maths']);
    });

    it('is reset when the scoped instances are flushed (request, job, Octane)', function () {
        analytics()->context(['leak' => true]);

        app()->forgetScopedInstances();

        analytics()->track('student.created');

        expect(AnalyticsEvent::query()->sole()->properties)->toBe([]);
    });

    it('is sanitized like any other property', function () {
        analytics()->context(['api_key' => 'x', 'module' => 'm']);
        analytics()->track('a.b');

        expect(AnalyticsEvent::query()->sole()->properties)->toBe(['module' => 'm']);
    });
});

describe('failure policy', function () {
    beforeEach(function () {
        $this->app->instance(AnalyticsDriver::class, new class implements AnalyticsDriver
        {
            public function insert(AnalyticsEventData $event): void
            {
                throw new RuntimeException('db down: secret-connection-detail');
            }

            public function paginateEvents(DateRange $range, array $eventNames, ?string $category, int $perPage, int $page): LengthAwarePaginator
            {
                throw new LogicException;
            }

            public function countDay(DateRange $day): array
            {
                return [];
            }

            public function storeAggregates(string $date, array $rows): void {}

            public function aggregatedCounts(DateRange $range, array $eventNames = []): array
            {
                return [];
            }

            public function eventNamesWithCategory(DateRange $range, string $category): array
            {
                return [];
            }

            public function storedCategories(DateRange $range): array
            {
                return [];
            }

            public function oldestEventAt(): ?CarbonImmutable
            {
                return null;
            }

            public function pruneEventsBefore(CarbonImmutable $before, int $batchSize): int
            {
                return 0;
            }
        });
        $this->app->forgetInstance(EventCollector::class);
        $this->app->forgetScopedInstances();
    });

    it('swallows and logs persistence failures without leaking properties', function () {
        Log::spy();

        analytics()->track('a.b', ['email' => 'private@example.com']);

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $context['event'] === 'a.b' && ! str_contains(json_encode($context), 'private@example.com');
        });
    });

    it('rethrows when fail_silently is disabled', function () {
        config(['analytics.errors.fail_silently' => false]);

        analytics()->track('a.b');
    })->throws(RuntimeException::class);

    it('rethrows in debug mode', function () {
        config(['app.debug' => true]);

        analytics()->track('a.b');
    })->throws(RuntimeException::class);

    it('never swallows programmer errors', function () {
        analytics()->track('Bad Name');
    })->throws(InvalidArgumentException::class);
});
