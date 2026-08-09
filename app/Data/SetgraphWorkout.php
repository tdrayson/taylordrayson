<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A gym session parsed from Setgraph's plain-text share sheet. `sets` is the flat
 * list stored on `activities.meta.sets`; `duration` and `label` come from the
 * summary line and are only a fallback until Strava syncs the real timing.
 */
final readonly class SetgraphWorkout implements Arrayable, JsonSerializable
{
    /**
     * @param  list<array{exercise: string, reps: int, weight_kg: float}>  $sets
     */
    public function __construct(
        public array $sets,
        public ?int $duration = null,
        public ?string $label = null,
    ) {}

    /**
     * A share that parsed to nothing usable is not worth recording.
     */
    public function isEmpty(): bool
    {
        return $this->sets === [];
    }

    /**
     * Total kg lifted across every set, ignoring bodyweight sets.
     */
    public function volume(): float
    {
        return array_sum(array_map(
            fn (array $set): float => $set['reps'] * $set['weight_kg'],
            $this->sets,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sets' => $this->sets,
            'duration' => $this->duration,
            'label' => $this->label,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
