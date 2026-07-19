<?php

namespace App\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateLocationMap
{
    private const ZOOM = 15;

    private const MARKER_COLOR = '8541C8';

    public function __invoke(Model&HasMedia $model, ?string $markerColor = null): ?Media
    {
        $color = $markerColor ?? self::MARKER_COLOR;
        $lat = $model->getAttribute('latitude');
        $lng = $model->getAttribute('longitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        $token = config('services.mapbox.token');

        if (! $token) {
            return null;
        }

        $styles = [
            'mapbox/light-v11' => 'map',
            'mapbox/dark-v11' => 'map_dark',
        ];

        $last = null;

        foreach ($styles as $style => $collection) {
            $marker = 'pin-l+'.$color."({$lng},{$lat})";
            $center = "{$lng},{$lat},".self::ZOOM;
            $url = "https://api.mapbox.com/styles/v1/{$style}/static/{$marker}/{$center}/800x500@2x?access_token={$token}";

            try {
                $response = Http::get($url);
            } catch (ConnectionException) {
                continue;
            }

            if ($response->failed()) {
                continue;
            }

            $last = $model->addMediaFromString($response->body())
                ->usingFileName(Str::uuid().'.png')
                ->toMediaCollection($collection);
        }

        return $last;
    }
}
