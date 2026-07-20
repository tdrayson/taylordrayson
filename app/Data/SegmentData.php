<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single sleep-stage segment (seconds spent in that stage) for the timeline
 * breakdown bar.
 */
final readonly class SegmentData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $label,
        public string $stage,
        public int $seconds,
    ) {}

    /**
     * @return array{label: string, stage: string, seconds: int}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'stage' => $this->stage,
            'seconds' => $this->seconds,
        ];
    }

    /**
     * @return array{label: string, stage: string, seconds: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
