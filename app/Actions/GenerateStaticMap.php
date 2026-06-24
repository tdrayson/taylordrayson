<?php

namespace App\Actions;

use App\Models\Activity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateStaticMap
{
    public function __invoke(Activity $activity): ?Media
    {
        $polyline = $activity->meta['polyline'] ?? null;

        if (! $polyline) {
            return null;
        }

        if ($existing = $activity->getFirstMedia('map')) {
            return $existing;
        }

        $token = config('services.mapbox.token');
        $overlay = 'path-3+2E9E6A('.rawurlencode($polyline).')';

        $url = "https://api.mapbox.com/styles/v1/mapbox/light-v11/static/{$overlay}"
            .'/auto/800x500@2x'
            ."?access_token={$token}"
            .'&padding=40';

        $response = Http::get($url);

        if ($response->failed()) {
            return null;
        }

        return $activity->addMediaFromString($response->body())
            ->usingFileName(Str::uuid().'.png')
            ->toMediaCollection('map');
    }
}
