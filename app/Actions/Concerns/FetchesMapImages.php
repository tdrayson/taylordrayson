<?php

namespace App\Actions\Concerns;

use App\Exceptions\MapGenerationFailed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The fetch-and-store half of the three map generators (route, pin and arc),
 * which differ only in the URLs they build.
 */
trait FetchesMapImages
{
    /**
     * Fetch every style and store them together, or store none at all. Renderers
     * key off the light map, so storing only the dark one leaves an entry that
     * looks mapped in the database and unmapped on the page.
     *
     * @param  array<string, string|null>  $urls  collection name => URL, skipped when null
     * @return Media|null the last image stored, or null when there was nothing to fetch
     *
     * @throws MapGenerationFailed
     */
    protected function storeMapImages(Model&HasMedia $model, array $urls): ?Media
    {
        $urls = array_filter($urls);

        if ($urls === []) {
            return null;
        }

        // Fetch everything before storing anything, so a failure on the second
        // style cannot leave the first one written.
        $images = [];

        foreach ($urls as $collection => $url) {
            try {
                $response = Http::get($url);
            } catch (ConnectionException $exception) {
                throw new MapGenerationFailed(
                    "Could not reach Mapbox for the {$collection} image: {$exception->getMessage()}",
                    previous: $exception,
                );
            }

            if ($response->failed()) {
                throw new MapGenerationFailed("Mapbox returned {$response->status()} for the {$collection} image.");
            }

            $images[$collection] = $response->body();
        }

        $last = null;

        foreach ($images as $collection => $body) {
            $last = $model->addMediaFromString($body)
                ->usingFileName(Str::uuid().'.webp')
                ->toMediaCollection($collection);
        }

        return $last;
    }
}
