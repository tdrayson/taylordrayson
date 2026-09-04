<?php

use App\Actions\GenerateStaticMap;
use App\Exceptions\MapGenerationFailed;
use App\Models\Activity;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
    Storage::fake('public');
});

it('stores light and dark route maps from the activity polyline', function () {
    Saloon::fake(['api.mapbox.com*' => MockResponse::make(mapPng(), 200)]);

    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U_ulLnnqC']]);

    app(GenerateStaticMap::class)($activity);

    expect($activity->getFirstMediaUrl('map'))->not->toBe('');
    expect($activity->getFirstMediaUrl('map_dark'))->not->toBe('');

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'light-v11'));
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'dark-v11'));
});

it('returns null when the activity has no polyline', function () {
    $activity = Activity::factory()->create(['meta' => []]);

    expect(app(GenerateStaticMap::class)($activity))->toBeNull();
});

/**
 * A real activity ("Night Walk", 6 Aug 2026) was left with a dark map and no
 * light one when a single Mapbox call failed. Every renderer keys off the light
 * image, so the map vanished from the page while the entry looked mapped in the
 * database, and nothing retried it because the generator reported success.
 */
it('stores nothing at all when one of the two styles fails', function () {
    Saloon::fake([
        'light-v11*' => MockResponse::make(mapPng(), 200),
        'dark-v11*' => MockResponse::make('', 500),
    ]);

    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U_ulLnnqC']]);

    expect(fn () => app(GenerateStaticMap::class)($activity))->toThrow(MapGenerationFailed::class);

    // The light image fetched fine; storing it would have produced exactly the
    // half-mapped entry this is here to prevent. With neither stored, the
    // activity stays eligible for the job's retry and the maps:generate sweep.
    expect($activity->getFirstMedia('map'))->toBeNull()
        ->and($activity->getFirstMedia('map_dark'))->toBeNull();
});
