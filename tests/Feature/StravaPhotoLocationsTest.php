<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
        'queue.default' => 'sync',
    ]);
    Cache::flush();
    Storage::fake('public');
});

/** An activity with one already-downloaded photo, as the backfill will find it. */
function activityWithStoredPhoto(string $sourceId, string $capturedAt): Activity
{
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => $sourceId]);
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('photo-a.jpg')
        ->withCustomProperties(['captured_at' => $capturedAt])
        ->toMediaCollection('cover');

    return $activity;
}

function fakeStravaLocations(array $summaries, array $streams): void
{
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        // Two full pages (summaries, then empty to end pagination) per command
        // invocation. One of the tests below runs the command twice.
        '*/athlete/activities*' => Http::sequence()
            ->push($summaries)->push([])
            ->push($summaries)->push([]),
        '*/streams*' => Http::response($streams),
    ]);
}

it('writes coordinates onto existing media without re-downloading the image', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media->getCustomProperty('latitude'))->toBe(51.1)
        ->and($media->getCustomProperty('longitude'))->toBe(-0.1);

    // The whole point of this command: no image bytes are fetched again.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'cloudfront.net'));
});

it('leaves an out-of-window photo without coordinates', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T22:00:00Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();
});

it('skips media that already has coordinates unless forced', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');
    $media = $activity->getFirstMedia('cover');
    // Non-whole floats: custom_properties round-trips through JSON, and PHP's
    // json_encode(1.0) collapses to "1" (serialize_precision -1), which would
    // come back as an int and make this assertion fail for reasons unrelated
    // to the command under test.
    $media->setCustomProperty('latitude', 1.5);
    $media->setCustomProperty('longitude', 2.5);
    $media->save();

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')
        ->expectsOutputToContain('No photos to locate.')
        ->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->getCustomProperty('latitude'))->toBe(1.5);

    // Forcing re-derives it from the stream.
    $this->artisan('strava:photo-locations', ['--force' => true])->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->getCustomProperty('latitude'))->toBe(51.1);
});

// Guards against the exact defect fixed in SyncStravaPhotos: CarbonImmutable::parse()
// throws on a malformed non-empty string. A photo with a bad stored captured_at
// must be skipped on its own, not abort locating the activity's other photos.
it('skips a photo with malformed captured_at and continues locating the rest', function () {
    $activity = activityWithStoredPhoto('100', 'not-a-valid-date');
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('photo-b.jpg')
        ->withCustomProperties(['captured_at' => '2023-10-31T21:00:10Z'])
        ->toMediaCollection('photos');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 2]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    $activity->refresh();
    $cover = $activity->getFirstMedia('cover');
    $gallery = $activity->getMedia('photos')->first();

    expect($cover->hasCustomProperty('latitude'))->toBeFalse()
        ->and($gallery->getCustomProperty('latitude'))->toBe(51.1);
});

// Guards against fabricating a start from CarbonImmutable::parse(null): an
// activity whose Strava summary carries no start_date cannot be located and
// must be skipped, without ever spending a streams request on it.
it('skips an activity with no start_date rather than fabricating one', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');

    fakeStravaLocations(
        [['id' => 100, 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/streams'));
});
