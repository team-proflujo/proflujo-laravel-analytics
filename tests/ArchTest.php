<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Proflujo\Analytics')
    ->toUseStrictTypes();

arch('services do not depend on the dashboard layer')
    ->expect('Proflujo\Analytics\Services\EventCollector')
    ->not->toUse('Proflujo\Analytics\Http');

arch('event collection never talks to Telegram')
    ->expect('Proflujo\Analytics\AnalyticsManager')
    ->not->toUse('Proflujo\Analytics\Services\TelegramService');
