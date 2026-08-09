<?php

namespace App\Actions;

use App\Actions\Concerns\FetchesMapImages;
use App\Exceptions\MapGenerationFailed;
use App\Models\Activity;
use App\Support\StaticMap;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateStaticMap
{
    use FetchesMapImages;

    /**
     * @throws MapGenerationFailed
     */
    public function __invoke(Activity $activity): ?Media
    {
        $polyline = $activity->meta['polyline'] ?? null;

        if (! $polyline) {
            return null;
        }

        return $this->storeMapImages($activity, [
            'map' => StaticMap::route($polyline),
            'map_dark' => StaticMap::route($polyline, style: 'mapbox/dark-v11'),
        ]);
    }
}
