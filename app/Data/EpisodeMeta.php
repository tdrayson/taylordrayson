<?php

namespace App\Data;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use JsonSerializable;

/**
 * The `episodes.meta` column, typed.
 *
 * `$ids` and `$tmdb` are always objects, never null, so a reader can say
 * `$episode->meta->tmdb->genres` without a null check on the way.
 */
final readonly class EpisodeMeta implements Arrayable, Castable, JsonSerializable
{
    /** Keys spelled out below. Everything else survives via `$extra`. */
    private const NAMED = ['season', 'episode', 'show_title', 'show_slug', 'runtime', 'ids', 'tmdb'];

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?int $season = null,
        public ?int $episode = null,
        public ?string $showTitle = null,
        public ?string $showSlug = null,
        public ?int $runtime = null,
        public TraktIds $ids = new TraktIds,
        public TmdbMeta $tmdb = new TmdbMeta,
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function from(?array $meta): self
    {
        $meta ??= [];

        return new self(
            season: MetaValue::int($meta['season'] ?? null),
            episode: MetaValue::int($meta['episode'] ?? null),
            showTitle: MetaValue::string($meta['show_title'] ?? null),
            showSlug: MetaValue::string($meta['show_slug'] ?? null),
            runtime: MetaValue::int($meta['runtime'] ?? null),
            ids: TraktIds::from(is_array($meta['ids'] ?? null) ? $meta['ids'] : null),
            tmdb: TmdbMeta::from(is_array($meta['tmdb'] ?? null) ? $meta['tmdb'] : null),
            extra: Arr::except($meta, self::NAMED),
        );
    }

    /**
     * A copy with `$changes` (in stored spelling) laid over the top, so a
     * writer can update one key without restating the rest.
     *
     * @param  array<string, mixed>  $changes
     */
    public function merge(array $changes): self
    {
        return self::from([...$this->toArray(), ...$changes]);
    }

    /**
     * The stored spelling. Keys the source never set stay absent rather than
     * reappearing as null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return MetaValue::compact([
            'season' => $this->season,
            'episode' => $this->episode,
            'show_title' => $this->showTitle,
            'show_slug' => $this->showSlug,
            'runtime' => $this->runtime,
            'ids' => $this->ids->toArray(),
            'tmdb' => $this->tmdb->toArray(),
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
}
