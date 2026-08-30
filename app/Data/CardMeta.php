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
        public ?array $body,
        public ?string $brand,
        public ?string $brandLogo,
        private array $present,
        public ?string $address = null,
        public ?string $backdrop = null,
        public ?string $category = null,
        public ?array $previews = null,
        public ?array $favicons = null,
    ) {}

    /**
     * No meta at all (Calorie, Project).
     */
    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, []);
    }

    /**
     * Media: the wide artwork behind a film or episode. Its own key rather than
     * a photo, so the card renders it as context and not as something to open
     * in the lightbox.
     */
    public static function backdrop(?string $backdrop): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, ['backdrop'], null, $backdrop);
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
     * both rather than one-or-other, with the address beneath and Foursquare's
     * category as a label.
     *
     * @param  list<PhotoData>  $photos
     */
    public static function checkin(array $photos, ?string $map, ?string $mapDark, ?string $address, ?string $category): self
    {
        return new self(null, $photos, $map, $mapDark, null, null, null, null, null, null, ['photos', 'map', 'mapDark', 'address', 'category'], $address, null, $category);
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
     * Note: body, photos, previews, favicons.
     *
     * A note is the entry itself rather than a summary of one, so the feed
     * renders its whole document. The resolved link data rides along on the
     * card because every feed surface needs it, unlike the entry page, which
     * provides it once for the page.
     *
     * @param  array<int, array<string, mixed>>  $body
     * @param  list<PhotoData>  $photos
     * @param  array<string, array<string, mixed>>  $previews
     * @param  array<string, string>  $favicons
     */
    public static function note(array $body, array $photos, array $previews = [], array $favicons = []): self
    {
        return new self(null, $photos, null, null, null, null, null, $body, null, null, ['body', 'photos', 'previews', 'favicons'], null, null, null, $previews, $favicons);
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
            'backdrop' => $this->backdrop,
            'category' => $this->category,
            'previews' => $this->previews,
            'favicons' => $this->favicons,
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
