<?php

namespace App\Actions;

use App\Actions\Concerns\FetchesMapImages;
use App\Exceptions\MapGenerationFailed;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateLocationMap
{
    use FetchesMapImages;

    private const ZOOM = 15;

    private const MARKER_COLOR = '8541C8';

    /**
     * @throws MapGenerationFailed
     */
    public function __invoke(Model&HasMedia $model, ?string $markerColor = null): ?Media
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

        $marker = 'pin-l+'.($markerColor ?? self::MARKER_COLOR)."({$lng},{$lat})";
        $center = "{$lng},{$lat},".self::ZOOM;

        $pin = fn (string $style): string => "https://api.mapbox.com/styles/v1/{$style}/static/{$marker}/{$center}/800x500@2x?access_token={$token}";

        return $this->storeMapImages($model, [
            'map' => $pin('mapbox/light-v11'),
            'map_dark' => $pin('mapbox/dark-v11'),
        ]);
    }
}
