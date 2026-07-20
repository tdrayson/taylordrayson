<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A season from TMDB's structure (`meta.season_list`), independent of which
 * episodes have actually been watched.
 */
final readonly class SeasonSummary implements Arrayable, JsonSerializable
{
    public function __construct(
        public ?int $number,
        public ?string $name,
        public ?int $episodeCount,
        public ?string $airDate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'name' => $this->name,
            'episodeCount' => $this->episodeCount,
            'airDate' => $this->airDate,
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
