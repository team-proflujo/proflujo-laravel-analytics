<?php

declare(strict_types=1);

namespace Proflujo\Analytics\DTOs;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class AnalyticsEventData
{
    public const string NAME_PATTERN = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/';

    public const int MAX_NAME_LENGTH = 128;

    /**
     * @param  array<array-key, mixed>  $properties  Already sanitized.
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $properties,
        public readonly ?string $category,
        public readonly ?string $userId,
        public readonly CarbonImmutable $occurredAt,
    ) {}

    public static function assertValidName(string $event): void
    {
        if (strlen($event) > self::MAX_NAME_LENGTH || preg_match(self::NAME_PATTERN, $event) !== 1) {
            throw new InvalidArgumentException(
                'Invalid analytics event name. Use lowercase dot-separated names such as "student.created" (max '.self::MAX_NAME_LENGTH.' characters).',
            );
        }
    }

    /** @return array{event_name: string, properties: array<array-key, mixed>, category: ?string, user_id: ?string, occurred_at: string} */
    public function toArray(): array
    {
        return [
            'event_name' => $this->eventName,
            'properties' => $this->properties,
            'category' => $this->category,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        /** @var array<array-key, mixed> $properties */
        $properties = is_array($data['properties'] ?? null) ? $data['properties'] : [];

        return new self(
            (string) ($data['event_name'] ?? ''),
            $properties,
            isset($data['category']) ? (string) $data['category'] : null,
            isset($data['user_id']) ? (string) $data['user_id'] : null,
            CarbonImmutable::parse((string) ($data['occurred_at'] ?? 'now'), 'UTC'),
        );
    }
}
