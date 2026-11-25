<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Tests\Support;

use Proflujo\Analytics\Tests\TestCase;

abstract class DashboardDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('analytics.dashboard.enabled', false);
    }
}
