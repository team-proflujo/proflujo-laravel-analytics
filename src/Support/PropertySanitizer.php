<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Support;

use BackedEnum;
use DateTimeInterface;
use UnitEnum;

/**
 * Turns arbitrary developer input into a bounded, JSON-safe, privacy-filtered array.
 * Rejected values are never logged and never echoed back.
 */
final class PropertySanitizer
{
    private const int MAX_STRING_LENGTH = 1000;

    private int $budget = 0;

    private bool $truncated = false;

    /**
     * @param  list<string>  $blockedKeys
     */
    public function __construct(
        private readonly array $blockedKeys,
        private readonly int $maxProperties,
        private readonly int $maxDepth,
        private readonly int $maxPayloadBytes,
    ) {}

    public static function fromConfig(): self
    {
        $blocked = config('analytics.privacy.blocked_keys', []);

        return new self(
            array_values(array_filter(is_array($blocked) ? $blocked : [], is_string(...))),
            max(1, self::int('analytics.collection.max_properties', 50)),
            max(1, self::int('analytics.collection.max_depth', 5)),
            max(64, self::int('analytics.collection.max_payload_bytes', 8192)),
        );
    }

    /**
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>
     */
    public function sanitize(array $properties): array
    {
        $this->budget = $this->maxProperties;
        $this->truncated = false;

        $clean = $this->walk($properties, 1);

        while ($clean !== [] && $this->encodedSize($clean) > $this->maxPayloadBytes) {
            array_pop($clean);
            $this->truncated = true;
        }

        if ($this->truncated) {
            $clean['_truncated'] = true;
        }

        return $clean;
    }

    public function isBlocked(string $key): bool
    {
        $normalized = '_'.trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key))), '_').'_';

        foreach ($this->blockedKeys as $blocked) {
            $needle = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($blocked)), '_');

            if ($needle !== '' && str_contains($normalized, '_'.$needle.'_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function walk(array $values, int $depth): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if ($this->budget <= 0) {
                $this->truncated = true;

                break;
            }

            if (is_string($key) && $this->isBlocked($key)) {
                continue;
            }

            $this->budget--;

            if (is_array($value)) {
                if ($depth >= $this->maxDepth) {
                    $this->truncated = true;

                    continue;
                }

                $out[$key] = $this->walk($value, $depth + 1);

                continue;
            }

            $out[$key] = $this->scalar($value);
        }

        return $out;
    }

    private function scalar(mixed $value): bool|int|float|string|null
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_float($value) => is_finite($value) ? $value : null,
            is_string($value) => $this->string($value),
            $value instanceof BackedEnum => is_string($value->value) ? $this->string($value->value) : $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => '[unsupported]',
        };
    }

    private function string(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return '[binary]';
        }

        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            $this->truncated = true;

            return mb_substr($value, 0, self::MAX_STRING_LENGTH);
        }

        return $value;
    }

    /** @param  array<array-key, mixed>  $values */
    private function encodedSize(array $values): int
    {
        $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? PHP_INT_MAX : strlen($json);
    }

    private static function int(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
