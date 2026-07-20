<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The `meta` grab-bag on a timeline card. Every field is nullable/optional;
 * each producer uses one of the named constructors below, which records
 * exactly which keys that producer includes, so the serialised `meta` array
 * matches that producer's pre-DTO array (no keys are ever added for fields a
 * given type never populated).
 */
final readonly class CardMeta implements Arrayable, JsonSerializable
{
    /**
     * @param  ?list<PhotoData>  $photos
     * @param  ?list<SegmentData>  $segments
     * @param  list<string>  $present  Keys to include when serialising.
     */
    private function __construct(
        public ?string $polyline,
        public ?array $photos,
        public ?string $map,
        public ?string $mapDark,
        public ?array $segments,
        public ?MediaData $media,
        public ?RouteData $route,
        public ?string $body,
        private array $present,
    ) {}

    /**
     * No meta at all (Calorie, Media, Project).
     */
    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, null, []);
    }

    /**
     * Activity: polyline, photos, map, mapDark.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function activity(?string $polyline, array $photos, ?string $map, ?string $mapDark): self
    {
        return new self($polyline, $photos, $map, $mapDark, null, null, null, null, ['polyline', 'photos', 'map', 'mapDark']);
    }

    /**
     * Sleep: segments.
     *
     * @param  list<SegmentData>  $segments
     */
    public static function sleep(array $segments): self
    {
        return new self(null, null, null, null, $segments, null, null, null, ['segments']);
    }

    /**
     * Event: photos, map, mapDark.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function event(array $photos, ?string $map, ?string $mapDark): self
    {
        return new self(null, $photos, $map, $mapDark, null, null, null, null, ['photos', 'map', 'mapDark']);
    }

    /**
     * Appearance/Podcast: media.
     */
    public static function media(MediaData $media): self
    {
        return new self(null, null, null, null, null, $media, null, null, ['media']);
    }

    /**
     * Flight: route, map, mapDark.
     */
    public static function route(RouteData $route, ?string $map, ?string $mapDark): self
    {
        return new self(null, null, $map, $mapDark, null, null, $route, null, ['route', 'map', 'mapDark']);
    }

    /**
     * Checkin/Fuel: map, mapDark.
     */
    public static function locationMap(?string $map, ?string $mapDark): self
    {
        return new self(null, null, $map, $mapDark, null, null, null, null, ['map', 'mapDark']);
    }

    /**
     * Article: photos.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function photos(array $photos): self
    {
        return new self(null, $photos, null, null, null, null, null, null, ['photos']);
    }

    /**
     * Note: body, photos.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function note(string $body, array $photos): self
    {
        return new self(null, $photos, null, null, null, null, null, $body, ['body', 'photos']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = [
            'polyline' => $this->polyline,
            'photos' => $this->photos !== null ? array_map(fn (PhotoData $photo): array => $photo->toArray(), $this->photos) : null,
            'map' => $this->map,
            'mapDark' => $this->mapDark,
            'segments' => $this->segments !== null ? array_map(fn (SegmentData $segment): array => $segment->toArray(), $this->segments) : null,
            'media' => $this->media?->toArray(),
            'route' => $this->route?->toArray(),
            'body' => $this->body,
        ];

        $result = [];

        foreach ($this->present as $key) {
            $result[$key] = $values[$key];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
