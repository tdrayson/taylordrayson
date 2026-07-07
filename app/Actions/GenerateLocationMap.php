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
    private const ZOOM = 14;

    public function __invoke(Model&HasMedia $model): ?Media
    {
        $lat = $model->getAttribute('latitude');
        $lng = $model->getAttribute('longitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        $token = config('services.mapbox.token');

        if (! $token) {
            return null;
        }

        $marker = "pin-s+2E9E6A({$lng},{$lat})";
        $center = "{$lng},{$lat},".self::ZOOM;

        $url = "https://api.mapbox.com/styles/v1/mapbox/light-v11/static/{$marker}/{$center}/800x500@2x?access_token={$token}";

        try {
            $response = Http::get($url);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        return $model->addMediaFromString($response->body())
            ->usingFileName(Str::uuid().'.png')
            ->toMediaCollection('map');
    }
}
