<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Proflujo\Analytics\Support\DateRange;

/**
 * @property int $id
 * @property string $event_name
 * @property ?string $category
 * @property ?string $user_id
 * @property array<array-key, mixed>|null $properties
 * @property CarbonImmutable $occurred_at Localised to the application timezone on read.
 */
class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'analytics_events';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['properties' => 'array'];

    public function getConnectionName(): ?string
    {
        $connection = config('analytics.database.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    /** @return Attribute<CarbonImmutable, never> */
    protected function occurredAt(): Attribute
    {
        return Attribute::get(fn (mixed $value): CarbonImmutable => CarbonImmutable::parse((string) $value, 'UTC')->setTimezone(DateRange::timezone()));
    }
}
