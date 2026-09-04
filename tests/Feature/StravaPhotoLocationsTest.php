<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

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

/**
 * @param  array<int, array<string, mixed>>  $photos  Raw Strava photos payload for the activity.
 */
function fakeStravaLocations(array $summaries, array $streams, array $photos = []): void
{
    if ($photos === []) {
        // Default: one photo matching the stored `photo-a.jpg`, no own location,
        // so placement falls through to the stream (the pre-existing behaviour).
        $photos = [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')];
    }

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token']),
        // Two full pages (summaries, then empty to end pagination) per command
        // invocation. One of the tests below runs the command twice.
        '/athlete/activities*' => mockSequence([
            MockResponse::make($summaries),
            MockResponse::make([]),
            MockResponse::make($summaries),
        ]),
        '/photos*' => MockResponse::make($photos),
        '/streams*' => MockResponse::make($streams),
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
    Saloon::assertNotSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'cloudfront.net'));
});

it('clamps a finish-line photo taken shortly after the stream ended to the last point', function () {
    // Stream ends at 21:00:20Z; taken 2 minutes later, inside the 180s grace window
    // but outside the raw stream, like a finish-line photo taken after recording stopped.
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:02:20Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
        [stravaPhotoPayload('photo-a', '2023-10-31T21:02:20Z')],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media->getCustomProperty('latitude'))->toBe(51.2)
        ->and($media->getCustomProperty('longitude'))->toBe(-0.2);
});

it('places an existing photo from its Strava location without fetching the stream', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        ['time' => ['data' => [0, 10, 20]], 'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]]],
        [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z', [48.86, 2.35])],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    // The photo's own GPS wins over the stream, so the stream is never fetched.
    expect($media->getCustomProperty('latitude'))->toBe(48.86)
        ->and($media->getCustomProperty('longitude'))->toBe(2.35);
    Saloon::assertNotSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/streams'));
});

it('leaves an out-of-window photo without coordinates', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T22:00:00Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
        // No own location and captured an hour after the stream ended.
        [stravaPhotoPayload('photo-a', '2023-10-31T22:00:00Z')],
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

// A photo whose payload created_at is malformed (and has no own location) must
// be skipped on its own via the stream fallback's guard, not abort locating the
// activity's other photos.
it('skips a photo with malformed created_at and continues locating the rest', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');
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
        [
            stravaPhotoPayload('photo-a', 'not-a-valid-date'),
            stravaPhotoPayload('photo-b', '2023-10-31T21:00:10Z'),
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    $activity->refresh();
    $cover = $activity->getFirstMedia('cover');
    $gallery = $activity->getMedia('photos')->first();

    expect($cover->hasCustomProperty('latitude'))->toBeFalse()
        ->and($gallery->getCustomProperty('latitude'))->toBe(51.1);
});

// Guards against the same defect as the equivalent StravaPhotos test:
// CarbonImmutable::parse() throws InvalidFormatException on a non-empty but
// unparseable start_date, which would otherwise abort the whole backfill run
// rather than just skipping the one activity it cannot locate.
it('skips an activity with a malformed start_date rather than crashing', function () {
    $activity = activityWithStoredPhoto('100', '2023-10-31T21:00:10Z');

    fakeStravaLocations(
        [['id' => 100, 'start_date' => 'not-a-date', 'total_photo_count' => 1]],
        [
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ],
    );

    $this->artisan('strava:photo-locations')->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover')->hasCustomProperty('latitude'))->toBeFalse();
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

    Saloon::assertNotSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/streams'));
});
