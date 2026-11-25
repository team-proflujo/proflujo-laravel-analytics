<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $event_name
 * @property string $date
 * @property int $event_count
 * @property ?int $unique_users
 */
class AnalyticsDailyAggregate extends Model
{
    protected $table = 'analytics_daily_aggregates';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['event_count' => 'integer', 'unique_users' => 'integer'];

    public function getConnectionName(): ?string
    {
        $connection = config('analytics.database.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
}
