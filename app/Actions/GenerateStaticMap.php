<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateStaticMap
{
    public function __invoke(Activity $activity): ?Asset
    {
        $polyline = $activity->meta['polyline'] ?? null;

        if (! $polyline) {
            return null;
        }

        if ($activity->map) {
            return $activity->map;
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

        $date = $activity->occurred_at;
        $filename = Str::uuid().'.png';
        $path = $date->format('Y/m/d').'/'.$filename;

        Storage::disk('public')->put($path, $response->body());

        return Asset::create([
            'assetable_type' => $activity->getMorphClass(),
            'assetable_id' => $activity->getKey(),
            'type' => 'map',
            'path' => $path,
            'original_filename' => $filename,
            'width' => 1600,
            'height' => 1000,
            'mime_type' => 'image/png',
            'size_bytes' => strlen($response->body()),
            'order' => 0,
        ]);
    }
}
