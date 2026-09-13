<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A TV show's watch statistics for the show page header.
 */
final readonly class TvShowStats implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $episodesWatched,
        public ?int $seasons,
        public ?int $progress,
        public ?string $watchSpan,
        public float $totalHours,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'episodesWatched' => $this->episodesWatched,
            'seasons' => $this->seasons,
            'progress' => $this->progress,
            'watchSpan' => $this->watchSpan,
            'totalHours' => $this->totalHours,
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
