<?php

namespace App\Data;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use JsonSerializable;

/**
 * The `series.meta` column, typed: show-level counts from Trakt plus the TMDB
 * enrichment block and season structure.
 *
 * `$seasonList` hydrates straight into the SeasonSummary DTOs the show page
 * renders. They serialise to camelCase for the page but are stored in TMDB's
 * snake_case, so this class owns the translation both ways: the DTO that owns
 * the column owns how the column is spelled.
 */
final readonly class SeriesMeta implements Arrayable, Castable, JsonSerializable
{
    /** Keys spelled out below. Everything else survives via `$extra`. */
    private const NAMED = ['ids', 'aired_episodes', 'seasons', 'rating', 'tmdb', 'season_list'];

    /**
     * @param  list<SeasonSummary>  $seasonList
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public TraktIds $ids = new TraktIds,
        public ?int $airedEpisodes = null,
        public ?int $seasons = null,
        public int|float|null $rating = null,
        public TmdbMeta $tmdb = new TmdbMeta,
        public array $seasonList = [],
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function from(?array $meta): self
    {
        $meta ??= [];

        return new self(
            ids: TraktIds::from(is_array($meta['ids'] ?? null) ? $meta['ids'] : null),
            airedEpisodes: MetaValue::int($meta['aired_episodes'] ?? null),
            seasons: MetaValue::int($meta['seasons'] ?? null),
            rating: MetaValue::number($meta['rating'] ?? null),
            tmdb: TmdbMeta::from(is_array($meta['tmdb'] ?? null) ? $meta['tmdb'] : null),
            seasonList: self::seasonsFrom($meta['season_list'] ?? null),
            extra: Arr::except($meta, self::NAMED),
        );
    }

    /**
     * A copy with `$changes` (in stored spelling) laid over the top.
     *
     * @param  array<string, mixed>  $changes
     */
    public function merge(array $changes): self
    {
        return self::from([...$this->toArray(), ...$changes]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return MetaValue::compact([
            'ids' => $this->ids->toArray(),
            'aired_episodes' => $this->airedEpisodes,
            'seasons' => $this->seasons,
            'rating' => $this->rating,
            'tmdb' => $this->tmdb->toArray(),
            'season_list' => array_map(self::seasonToStorage(...), $this->seasonList),
        ]) + $this->extra;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new MetaCast(self::class);
    }

    /**
     * @return list<SeasonSummary>
     */
    private static function seasonsFrom(mixed $seasons): array
    {
        if (! is_array($seasons)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $season): SeasonSummary => new SeasonSummary(
                number: MetaValue::int(is_array($season) ? ($season['number'] ?? null) : null),
                name: MetaValue::string(is_array($season) ? ($season['name'] ?? null) : null),
                episodeCount: MetaValue::int(is_array($season) ? ($season['episode_count'] ?? null) : null),
                airDate: MetaValue::string(is_array($season) ? ($season['air_date'] ?? null) : null),
            ),
            array_filter($seasons, is_array(...)),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function seasonToStorage(SeasonSummary $season): array
    {
        return [
            'number' => $season->number,
            'name' => $season->name,
            'episode_count' => $season->episodeCount,
            'air_date' => $season->airDate,
        ];
    }
}
