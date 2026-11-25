<?php

declare(strict_types=1);

use Proflujo\Analytics\Support\EventDefinitionRegistry;

it('resolves configured metadata', function () {
    config(['analytics-events.events' => ['payment.completed' => ['name' => 'Payment Completed', 'description' => 'Paid.', 'category' => 'Payments']]]);

    $registry = new EventDefinitionRegistry;

    expect($registry->name('payment.completed'))->toBe('Payment Completed')
        ->and($registry->description('payment.completed'))->toBe('Paid.')
        ->and($registry->category('payment.completed'))->toBe('Payments')
        ->and($registry->namesInCategory('Payments'))->toBe(['payment.completed']);
});

it('generates readable fallbacks for undefined events', function () {
    $registry = new EventDefinitionRegistry;

    expect($registry->name('inventory.stock.adjusted'))->toBe('Inventory Stock Adjusted')
        ->and($registry->category('inventory.stock.adjusted'))->toBe('Uncategorized')
        ->and($registry->description('inventory.stock.adjusted'))->toBeNull();
});

it('reads config dynamically', function () {
    $registry = new EventDefinitionRegistry;
    expect($registry->name('a.b'))->toBe('A B');

    config(['analytics-events.events' => ['a.b' => ['name' => 'Renamed']]]);

    expect($registry->name('a.b'))->toBe('Renamed');
});
