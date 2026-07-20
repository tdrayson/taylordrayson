<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The full show-page payload: header, watch stats, watched episodes grouped by
 * season then date, and TMDB's season overview. toArray() emits the four props
 * the SeriesShow page expects.
 */
final readonly class SeriesShow implements Arrayable, JsonSerializable
{
    /**
     * @param  list<SeasonGroup>  $seasons
     * @param  list<SeasonSummary>  $seasonList
     */
    public function __construct(
        public SeriesHeader $series,
        public SeriesStats $stats,
        public array $seasons,
        public array $seasonList,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'series' => $this->series->toArray(),
            'stats' => $this->stats->toArray(),
            'seasons' => array_map(fn (SeasonGroup $group): array => $group->toArray(), $this->seasons),
            'seasonList' => array_map(fn (SeasonSummary $season): array => $season->toArray(), $this->seasonList),
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
