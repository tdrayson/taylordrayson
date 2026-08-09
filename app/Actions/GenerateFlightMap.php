<?php

namespace App\Actions;

use App\Actions\Concerns\FetchesMapImages;
use App\Exceptions\MapGenerationFailed;
use App\Models\Flight;
use App\Support\StaticMap;
use App\Support\TypeColors;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateFlightMap
{
    use FetchesMapImages;

    /**
     * @throws MapGenerationFailed
     */
    public function __invoke(Flight $flight): ?Media
    {
        $origin = $flight->origin;
        $destination = $flight->destination;

        if ($origin?->latitude === null || $origin?->longitude === null || $destination?->latitude === null || $destination?->longitude === null) {
            return null;
        }

        $color = TypeColors::hex('flight');
        $arc = fn (string $style): ?string => StaticMap::arc(
            (float) $origin->longitude,
            (float) $origin->latitude,
            (float) $destination->longitude,
            (float) $destination->latitude,
            $color,
            style: $style,
        );

        return $this->storeMapImages($flight, [
            'map' => $arc('mapbox/light-v11'),
            'map_dark' => $arc('mapbox/dark-v11'),
        ]);
    }
}
