<?php

namespace App\Data\Hub;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use JsonSerializable;

/**
 * One data type in the hub's Entries block: how much of it there is, and when
 * the newest entry arrived.
 */
final readonly class TypeCount implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $type,
        public string $label,
        public string $icon,
        public int $count,
        public ?Carbon $newest,
        public bool $synced,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'count' => $this->count,
            'lag' => $this->lag(),
            'synced' => $this->synced,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** Formatted here rather than in the client, as every other date is. */
    private function lag(): string
    {
        if ($this->newest === null) {
            return $this->count === 0 ? 'nothing yet' : 'unknown';
        }

        return $this->newest->diffForHumans();
    }
}
