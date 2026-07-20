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
     * @param  list<EpisodeRow>  $episodes
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
            'episodes' => array_map(fn (EpisodeRow $episode): array => $episode->toArray(), $this->episodes),
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
