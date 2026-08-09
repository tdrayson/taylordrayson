<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use JsonSerializable;

/**
 * The `ids` block Trakt attaches to every film, episode and show.
 *
 * Only the ids we actually use are named. Trakt sends others (and nests a
 * whole `plex` object inside), so anything unnamed round-trips through
 * `$extra` rather than being dropped the next time the row is saved.
 */
final readonly class TraktIds implements Arrayable, JsonSerializable
{
    private const NAMED = ['trakt', 'slug', 'imdb', 'tmdb', 'tvdb'];

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?int $trakt = null,
        public ?string $slug = null,
        public ?string $imdb = null,
        public ?int $tmdb = null,
        public ?int $tvdb = null,
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $ids
     */
    public static function from(?array $ids): self
    {
        $ids ??= [];

        return new self(
            trakt: MetaValue::int($ids['trakt'] ?? null),
            slug: MetaValue::string($ids['slug'] ?? null),
            imdb: MetaValue::string($ids['imdb'] ?? null),
            tmdb: MetaValue::int($ids['tmdb'] ?? null),
            tvdb: MetaValue::int($ids['tvdb'] ?? null),
            extra: Arr::except($ids, self::NAMED),
        );
    }

    /**
     * The stored spelling, unnamed ids included.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return MetaValue::compact([
            'trakt' => $this->trakt,
            'slug' => $this->slug,
            'imdb' => $this->imdb,
            'tmdb' => $this->tmdb,
            'tvdb' => $this->tvdb,
        ]) + $this->extra;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
