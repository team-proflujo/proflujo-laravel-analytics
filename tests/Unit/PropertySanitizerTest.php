<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Proflujo\Analytics\Support\PropertySanitizer;

enum SanitizerStatus: string
{
    case Paid = 'paid';
}

function sanitizer(int $props = 50, int $depth = 5, int $bytes = 8192): PropertySanitizer
{
    return new PropertySanitizer(['password', 'token', 'api_key', 'session', 'cvv'], $props, $depth, $bytes);
}

it('removes blocked keys recursively and case-insensitively', function () {
    $clean = sanitizer()->sanitize([
        'name' => 'Ada',
        'Password' => 'x',
        'nested' => ['apiKey' => 'x', 'ok' => 1, 'deeper' => ['access_token' => 'x', 'keep' => true]],
        'session_id' => 'abc',
    ]);

    expect($clean)->toBe(['name' => 'Ada', 'nested' => ['ok' => 1, 'deeper' => ['keep' => true]]]);
});

it('does not over-block keys that merely contain a blocked word', function () {
    expect(sanitizer()->sanitize(['tokenizer' => 'a', 'sessions_total' => 3]))->toBe(['tokenizer' => 'a', 'sessions_total' => 3]);
});

it('never serializes models or arbitrary objects', function () {
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->forceFill(['secret_value' => 'leak']);

    $clean = sanitizer()->sanitize(['model' => $model, 'object' => new stdClass, 'closure' => fn () => 1]);

    expect($clean)->toBe(['model' => '[unsupported]', 'object' => '[unsupported]', 'closure' => '[unsupported]'])
        ->and(json_encode($clean))->not->toContain('leak');
});

it('normalizes enums, dates, floats and binary strings', function () {
    $clean = sanitizer()->sanitize([
        'status' => SanitizerStatus::Paid,
        'at' => new DateTimeImmutable('2026-01-02 03:04:05 UTC'),
        'nan' => NAN,
        'bin' => "\xB1\x31",
    ]);

    expect($clean)->toBe(['status' => 'paid', 'at' => '2026-01-02T03:04:05+00:00', 'nan' => null, 'bin' => '[binary]']);
});

it('limits property count', function () {
    $clean = sanitizer(props: 3)->sanitize(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);

    expect($clean)->toBe(['a' => 1, 'b' => 2, 'c' => 3, '_truncated' => true]);
});

it('limits depth', function () {
    $clean = sanitizer(depth: 2)->sanitize(['l1' => ['l2' => ['l3' => 'x']], 'flat' => 1]);

    expect($clean)->toBe(['l1' => [], 'flat' => 1, '_truncated' => true]);
});

it('limits payload size', function () {
    $clean = sanitizer(bytes: 200)->sanitize(['a' => str_repeat('x', 150), 'b' => str_repeat('y', 150)]);

    expect(strlen((string) json_encode($clean)))->toBeLessThanOrEqual(220)
        ->and($clean)->toHaveKey('_truncated');
});

it('truncates very long strings', function () {
    $clean = sanitizer(bytes: 100000)->sanitize(['s' => str_repeat('é', 5000)]);

    expect(mb_strlen($clean['s']))->toBe(1000);
});
