<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use JsonSerializable;

/**
 * The `tmdb` block written by the EnrichMedia job, for both films (on Media)
 * and shows (on Series).
 */
final readonly class TmdbMeta implements Arrayable, JsonSerializable
{
    private const NAMED = ['id', 'status', 'network', 'genres', 'tagline', 'vote'];

    /**
     * @param  list<string>  $genres
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?int $id = null,
        public ?string $status = null,
        public ?string $network = null,
        public array $genres = [],
        public ?string $tagline = null,
        public ?float $vote = null,
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $tmdb
     */
    public static function from(?array $tmdb): self
    {
        $tmdb ??= [];

        return new self(
            id: MetaValue::int($tmdb['id'] ?? null),
            status: MetaValue::string($tmdb['status'] ?? null),
            network: MetaValue::string($tmdb['network'] ?? null),
            genres: MetaValue::strings($tmdb['genres'] ?? null),
            tagline: MetaValue::string($tmdb['tagline'] ?? null),
            vote: MetaValue::float($tmdb['vote'] ?? null),
            extra: Arr::except($tmdb, self::NAMED),
        );
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return MetaValue::compact([
            'id' => $this->id,
            'status' => $this->status,
            'network' => $this->network,
            'genres' => $this->genres,
            'tagline' => $this->tagline,
            'vote' => $this->vote,
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
