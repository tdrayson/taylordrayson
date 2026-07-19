<?php

namespace App\Actions;

use App\Models\Activity;
use App\Support\StaticMap;
use Illuminate\Http\Client\ConnectionException;
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

        $styles = [
            'map' => StaticMap::route($polyline),
            'map_dark' => StaticMap::route($polyline, style: 'mapbox/dark-v11'),
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

            $last = $activity->addMediaFromString($response->body())
                ->usingFileName(Str::uuid().'.png')
                ->toMediaCollection($collection);
        }

        return $last;
    }
}
