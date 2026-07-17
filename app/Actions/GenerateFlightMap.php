<?php

namespace App\Actions;

use App\Models\Flight;
use App\Support\StaticMap;
use App\Support\TypeColors;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateFlightMap
{
    public function __invoke(Flight $flight): ?Media
    {
        $origin = $flight->origin;
        $destination = $flight->destination;

        if ($origin?->latitude === null || $origin?->longitude === null || $destination?->latitude === null || $destination?->longitude === null) {
            return null;
        }

        $color = TypeColors::hex('flight');

        $styles = [
            'map' => StaticMap::arc((float) $origin->longitude, (float) $origin->latitude, (float) $destination->longitude, (float) $destination->latitude, $color),
            'map_dark' => StaticMap::arc((float) $origin->longitude, (float) $origin->latitude, (float) $destination->longitude, (float) $destination->latitude, $color, style: 'mapbox/dark-v11'),
        ];

        $last = null;

        foreach ($styles as $collection => $url) {
            if ($url === null) {
                continue;
            }

            try {
                $response = Http::get($url);
            } catch (ConnectionException) {
                continue;
            }

            if ($response->failed()) {
                continue;
            }

            $last = $flight->addMediaFromString($response->body())
                ->usingFileName(Str::uuid().'.png')
                ->toMediaCollection($collection);
        }

        return $last;
    }
}
