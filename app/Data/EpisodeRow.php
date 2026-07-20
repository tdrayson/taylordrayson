<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single watched episode within a series show page's date group.
 */
final readonly class EpisodeRow implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $id,
        public int|string|null $season,
        public int|string|null $episode,
        public string $title,
        public string $occurredAt,
        public int|float|string|null $rating,
        public string $url,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'season' => $this->season,
            'episode' => $this->episode,
            'title' => $this->title,
            'occurredAt' => $this->occurredAt,
            'rating' => $this->rating,
            'url' => $this->url,
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
