<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Support;

use Illuminate\Support\Str;

/**
 * Resolves display metadata for event identifiers from config/analytics-events.php.
 * Config is read on every call so applications may change it at runtime.
 */
final class EventDefinitionRegistry
{
    public const string UNCATEGORIZED = 'Uncategorized';

    /** @return array{name: string, description: ?string, category: string, defined: bool} */
    public function resolve(string $event): array
    {
        $definition = $this->definitions()[$event] ?? null;

        return [
            'name' => $this->stringOrNull($definition['name'] ?? null) ?? $this->fallbackName($event),
            'description' => $this->stringOrNull($definition['description'] ?? null),
            'category' => $this->stringOrNull($definition['category'] ?? null) ?? self::UNCATEGORIZED,
            'defined' => $definition !== null,
        ];
    }

    public function name(string $event): string
    {
        return $this->resolve($event)['name'];
    }

    public function description(string $event): ?string
    {
        return $this->resolve($event)['description'];
    }

    public function category(string $event): string
    {
        return $this->resolve($event)['category'];
    }

    /** @return list<string> */
    public function namesInCategory(string $category): array
    {
        return array_values(array_filter(
            array_keys($this->definitions()),
            fn (string $event): bool => $this->category($event) === $category,
        ));
    }

    /** @return list<string> Defined events that carry an explicit category. */
    public function categorizedNames(): array
    {
        return array_values(array_filter(
            array_keys($this->definitions()),
            fn (string $event): bool => $this->category($event) !== self::UNCATEGORIZED,
        ));
    }

    /** @return list<string> */
    public function categories(): array
    {
        $categories = array_map(fn (string $event): string => $this->category($event), array_keys($this->definitions()));

        return array_values(array_unique($categories));
    }

    public function fallbackName(string $event): string
    {
        return Str::of($event)->replace(['.', '_', '-'], ' ')->squish()->title()->toString();
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        $events = config('analytics-events.events', []);
        $definitions = [];

        if (is_array($events)) {
            foreach ($events as $key => $definition) {
                if (is_string($key) && is_array($definition)) {
                    $definitions[$key] = $definition;
                }
            }
        }

        return $definitions;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
