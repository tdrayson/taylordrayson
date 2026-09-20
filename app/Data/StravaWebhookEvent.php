<?php

namespace App\Data;

use App\Enums\StravaAspect;
use App\Enums\StravaObjectType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One event pushed to the webhook callback.
 *
 * The payload names an object rather than carrying it, so everything here is an
 * id and a verb; the detail is a separate read. `objectType` and `aspect` are
 * nullable because a value Strava adds later must be a logged no-op rather than
 * an exception in a queued job.
 */
final readonly class StravaWebhookEvent implements Arrayable, JsonSerializable
{
    public function __construct(
        public ?StravaObjectType $objectType,
        public ?StravaAspect $aspect,
        public string $objectId,
        public string $ownerId,
        public ?int $subscriptionId,
        public CarbonImmutable $eventTime,
        /** @var array<string, mixed> Which fields changed, or `authorized: "false"` on an athlete event. */
        public array $updates,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            StravaObjectType::tryFrom((string) ($payload['object_type'] ?? '')),
            StravaAspect::tryFrom((string) ($payload['aspect_type'] ?? '')),
            (string) ($payload['object_id'] ?? ''),
            (string) ($payload['owner_id'] ?? ''),
            isset($payload['subscription_id']) ? (int) $payload['subscription_id'] : null,
            CarbonImmutable::createFromTimestampUTC((int) ($payload['event_time'] ?? 0)),
            is_array($payload['updates'] ?? null) ? $payload['updates'] : [],
        );
    }

    /** Whether this says the athlete has revoked the app's access. */
    public function isDeauthorisation(): bool
    {
        return $this->objectType === StravaObjectType::Athlete
            && ($this->updates['authorized'] ?? null) === 'false';
    }

    /**
     * @return array{object_type: ?string, aspect_type: ?string, object_id: string, owner_id: string, subscription_id: ?int, event_time: string, updates: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'object_type' => $this->objectType?->value,
            'aspect_type' => $this->aspect?->value,
            'object_id' => $this->objectId,
            'owner_id' => $this->ownerId,
            'subscription_id' => $this->subscriptionId,
            'event_time' => $this->eventTime->toIso8601String(),
            'updates' => $this->updates,
        ];
    }

    /**
     * @return array{object_type: ?string, aspect_type: ?string, object_id: string, owner_id: string, subscription_id: ?int, event_time: string, updates: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
