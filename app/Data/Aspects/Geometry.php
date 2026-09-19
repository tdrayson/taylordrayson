<?php

namespace App\Data\Aspects;

/**
 * Where an entry happened, as a GeoJSON geometry. Its presence is what makes
 * .geojson available; no export declares the format directly.
 */
final readonly class Geometry
{
    /**
     * @param  'Point'|'LineString'  $type
     * @param  list<float>|list<array{0: float, 1: float}>  $coordinates  Longitude first, per GeoJSON.
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
        $coordinates = array_map(fn (array $point): array => [$point[1], $point[0]], $latLngs);

        return count($coordinates) < 2 ? null : new self('LineString', $coordinates);
    }

    /**
     * @return array{type: string, coordinates: array<mixed>}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'coordinates' => $this->coordinates];
    }
}
