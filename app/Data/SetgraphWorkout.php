<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A gym session shared out of Setgraph, already parsed from its plain-text
 * share-sheet format.
 *
 * `sets` is the flat list stored on `activities.meta.sets`, one entry per set
 * performed. `duration` comes from the summary line ("Other • 38 min") and is
 * only a fallback: Strava owns the real timing once it syncs. `label` is that
 * same line's leading word, kept for naming an activity Setgraph had to create
 * itself.
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
