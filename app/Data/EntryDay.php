<?php

namespace App\Data;

use App\Enums\ActivityLevel;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One day of timeline activity. Days still to come have no count or level.
 */
final readonly class EntryDay implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $date,
        public ?int $count,
        public ?ActivityLevel $level,
    ) {}

    /**
     * @return array{date: string, count: int|null, level: int|null}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'count' => $this->count,
            'level' => $this->level?->value,
        ];
    }

    /**
     * @return array{date: string, count: int|null, level: int|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
