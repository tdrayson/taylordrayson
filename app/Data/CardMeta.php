<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The `meta` grab-bag on a timeline card. Every field is optional; each producer
 * uses one of the named constructors below, which fixes exactly which keys that
 * producer emits.
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
        public ?string $brand,
        public ?string $brandLogo,
        private array $present,
        public ?string $address = null,
    ) {}

    /**
     * No meta at all (Calorie, Media, Project).
     */
    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, []);
    }

    /**
     * Activity: polyline, photos, map, mapDark.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function activity(?string $polyline, array $photos, ?string $map, ?string $mapDark): self
    {
        return new self($polyline, $photos, $map, $mapDark, null, null, null, null, null, null, ['polyline', 'photos', 'map', 'mapDark']);
    }

    /**
     * Sleep: segments.
     *
     * @param  list<SegmentData>  $segments
     */
    public static function sleep(array $segments): self
    {
        return new self(null, null, null, null, $segments, null, null, null, null, null, ['segments']);
    }

    /**
     * Event: photos, map, mapDark.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function event(array $photos, ?string $map, ?string $mapDark): self
    {
        return new self(null, $photos, $map, $mapDark, null, null, null, null, null, null, ['photos', 'map', 'mapDark']);
    }

    /**
     * Appearance/Podcast: media.
     */
    public static function media(MediaData $media): self
    {
        return new self(null, null, null, null, null, $media, null, null, null, null, ['media']);
    }

    /**
     * Flight: route, map, mapDark.
     */
    public static function route(RouteData $route, ?string $map, ?string $mapDark): self
    {
        return new self(null, null, $map, $mapDark, null, null, $route, null, null, null, ['route', 'map', 'mapDark']);
    }

    /**
     * Checkin: the check-in's own photos alongside the generated location map,
     * both rather than one-or-other, with the address beneath.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function checkin(array $photos, ?string $map, ?string $mapDark, ?string $address): self
    {
        return new self(null, $photos, $map, $mapDark, null, null, null, null, null, null, ['photos', 'map', 'mapDark', 'address'], $address);
    }

    /**
     * Fuel: map, mapDark, brand, brandLogo.
     */
    public static function fuel(?string $map, ?string $mapDark, ?string $brand, ?string $brandLogo): self
    {
        return new self(null, null, $map, $mapDark, null, null, null, null, $brand, $brandLogo, ['map', 'mapDark', 'brand', 'brandLogo']);
    }

    /**
     * Article: photos.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function photos(array $photos): self
    {
        return new self(null, $photos, null, null, null, null, null, null, null, null, ['photos']);
    }

    /**
     * Note: body, photos.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function note(string $body, array $photos): self
    {
        return new self(null, $photos, null, null, null, null, null, $body, null, null, ['body', 'photos']);
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
            'brand' => $this->brand,
            'brandLogo' => $this->brandLogo,
            'address' => $this->address,
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
