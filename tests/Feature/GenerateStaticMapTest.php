<?php

use App\Actions\GenerateStaticMap;
use App\Models\Activity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
    Storage::fake('public');
});

it('stores light and dark route maps from the activity polyline', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);

    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U_ulLnnqC']]);

    app(GenerateStaticMap::class)($activity);

    expect($activity->getFirstMediaUrl('map'))->not->toBe('');
    expect($activity->getFirstMediaUrl('map_dark'))->not->toBe('');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'light-v11'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'dark-v11'));
});

it('returns null when the activity has no polyline', function () {
    $activity = Activity::factory()->create(['meta' => []]);

    expect(app(GenerateStaticMap::class)($activity))->toBeNull();
});
