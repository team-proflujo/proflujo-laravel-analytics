<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Facades;

use Illuminate\Support\Facades\Facade;
use Proflujo\Analytics\AnalyticsManager;

/**
 * @method static void track(string $event, array<array-key, mixed> $properties = [], mixed $user = null, ?string $category = null)
 * @method static AnalyticsManager context(array<array-key, mixed> $properties)
 * @method static AnalyticsManager clearContext()
 * @method static bool enabled()
 * @method static array<string, mixed> instance()
 * @method static array<string, mixed> summary(\DateTimeInterface|string $from, \DateTimeInterface|string|null $to = null)
 * @method static int aggregate(\DateTimeInterface|string $from, \DateTimeInterface|string|null $to = null)
 *
 * @see AnalyticsManager
 */
final class Analytics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AnalyticsManager::class;
    }
}
