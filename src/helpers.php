<?php

declare(strict_types=1);

use Proflujo\Analytics\AnalyticsManager;

if (! function_exists('analytics')) {
    /**
     * Resolve the analytics manager: analytics()->track('student.created').
     */
    function analytics(): AnalyticsManager
    {
        return app(AnalyticsManager::class);
    }
}
