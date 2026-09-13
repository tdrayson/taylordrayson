<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Episodes of one season watched on a single date, anchored for the timeline
 * collapse link (`#watch-{date}`).
 */
final readonly class WatchDateGroup implements Arrayable, JsonSerializable
{
    /**
     * @param  list<TvEpisodeRow>  $episodes
     */
    public function __construct(
        public string $date,
        public string $anchor,
        public array $episodes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'anchor' => $this->anchor,
            'episodes' => array_map(fn (TvEpisodeRow $episode): array => $episode->toArray(), $this->episodes),
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
