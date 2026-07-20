<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A season's worth of watched episodes, grouped by watch-date.
 */
final readonly class SeasonGroup implements Arrayable, JsonSerializable
{
    /**
     * @param  list<WatchDateGroup>  $dates
     */
    public function __construct(
        public int $season,
        public array $dates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'season' => $this->season,
            'dates' => array_map(fn (WatchDateGroup $group): array => $group->toArray(), $this->dates),
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
