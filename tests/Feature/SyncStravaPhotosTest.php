<?php

use App\Actions\LocatePhotoOnRoute;
use App\Actions\SyncStravaPhotos;
use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');
    Http::fake(['*dgtzuqphqg23d.cloudfront.net*' => Http::response(fakeJpeg())]);
});

function syncStreams(): array
{
    return [
        'time' => ['data' => [0, 10, 20]],
        'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
    ];
}

it('stores a located photo with latitude and longitude custom properties', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    (new SyncStravaPhotos(new LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')],
        syncStreams(),
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
    );

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media->getCustomProperty('latitude'))->toBe(51.1)
        ->and($media->getCustomProperty('longitude'))->toBe(-0.1);
});

it('stores an out-of-window photo with no coordinate properties', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    // Taken an hour after the 20-second stream ended, like an Instagram upload.
    (new SyncStravaPhotos(new LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T22:00:00Z')],
        syncStreams(),
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
    );

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media)->not->toBeNull()
        ->and($media->hasCustomProperty('latitude'))->toBeFalse();
});

it('stores photos when no stream is supplied at all', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    $stored = (new SyncStravaPhotos(new LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')],
    );

    expect($stored)->toBe(1)
        ->and($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();
});

it('stores photos when the stream has no latlng key, as on an indoor activity', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    $stored = (new SyncStravaPhotos(new LocatePhotoOnRoute))(
        $activity,
        [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')],
        ['time' => ['data' => [0, 10, 20]], 'distance' => ['data' => [0, 5, 9]]],
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
    );

    expect($stored)->toBe(1)
        ->and($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();
});

it('stores a photo with malformed created_at and continues syncing subsequent photos', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    $stored = (new SyncStravaPhotos(new LocatePhotoOnRoute))(
        $activity,
        [
            stravaPhotoPayload('photo-bad-date', 'not-a-valid-date'),
            stravaPhotoPayload('photo-good-date', '2023-10-31T21:00:10Z'),
        ],
        syncStreams(),
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
    );

    expect($stored)->toBe(2);

    $cover = $activity->refresh()->getFirstMedia('cover');
    $photos = $activity->getMedia('photos');

    expect($cover)->not->toBeNull()
        ->and($cover->hasCustomProperty('latitude'))->toBeFalse()
        ->and($photos)->toHaveCount(1)
        ->and($photos[0]->hasCustomProperty('latitude'))->toBeTrue();
});
