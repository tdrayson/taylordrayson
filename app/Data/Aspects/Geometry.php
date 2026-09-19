<?php

namespace App\Data\Aspects;

/**
 * Where an entry happened, as a GeoJSON geometry. Its presence is what makes
 * .geojson available; no export declares the format directly.
 */
final readonly class Geometry
{
    /**
     * @param  'Point'|'LineString'|'MultiLineString'  $type
     * @param  list<float>|list<array{0: float, 1: float}>|list<list<array{0: float, 1: float}>>  $coordinates  Longitude first, per GeoJSON.
     */
    private function __construct(
        public string $type,
        public array $coordinates,
    ) {}

    public static function point(float $lat, float $lng): self
    {
        return new self('Point', [$lng, $lat]);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $latLngs  Points as [lat, lng]; flipped here so callers never have to remember.
     */
    public static function lineString(array $latLngs): ?self
    {
        $coordinates = self::flip($latLngs);

        return count($coordinates) < 2 ? null : new self('LineString', $coordinates);
    }

    /**
     * A route in multiple pieces, e.g. a great circle split at the
     * antimeridian so no single piece implies wrapping the wrong way round
     * the globe.
     *
     * @param  list<list<array{0: float, 1: float}>>  $segments  Each a list of [lat, lng] points.
     */
    public static function multiLineString(array $segments): ?self
    {
        $lines = array_values(array_filter(
            array_map(fn (array $latLngs): array => self::flip($latLngs), $segments),
            fn (array $line): bool => count($line) >= 2,
        ));

        return $lines === [] ? null : new self('MultiLineString', $lines);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $latLngs
     * @return list<array{0: float, 1: float}>
     */
    private static function flip(array $latLngs): array
    {
        return array_map(fn (array $point): array => [$point[1], $point[0]], $latLngs);
    }

    /**
     * @return array{type: string, coordinates: array<mixed>}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'coordinates' => $this->coordinates];
    }
}
