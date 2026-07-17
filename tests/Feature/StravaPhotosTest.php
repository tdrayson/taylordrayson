<?php

use App\Actions\SyncStravaPhotos;
use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);
    Cache::flush();
    Storage::fake('public');
});

/**
 * Fake the whole Strava surface: token, one page of summaries, per-activity
 * photos, and the CloudFront image download.
 *
 * @param  array<int, array<string, mixed>>  $summaries
 * @param  array<string, array<int, array<string, mixed>>>  $photosById
 * @param  array<string, mixed>  $extra
 */
function fakeStravaPhotos(array $summaries, array $photosById, array $extra = []): void
{
    $responses = [
        '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        '*dgtzuqphqg23d.cloudfront.net*' => Http::response(fakeJpeg()),
        '*/athlete/activities*' => Http::sequence()
            ->push($summaries)
            ->push([]),
    ];

    foreach ($photosById as $id => $photos) {
        $responses["*/activities/{$id}/photos*"] = Http::response($photos);
    }

    Http::fake(array_merge($responses, $extra));
}

it('stores the first photo as cover and the rest in the gallery', function () {
    Http::fake(['https://cdn.example/*' => Http::response(fakeJpeg(), 200)]);

    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '1']);

    $count = app(SyncStravaPhotos::class)($activity, [
        ['unique_id' => 'a', 'urls' => ['2048' => 'https://cdn.example/a.jpg']],
        ['unique_id' => 'b', 'urls' => ['2048' => 'https://cdn.example/b.jpg']],
        ['unique_id' => 'c', 'urls' => ['2048' => 'https://cdn.example/c.jpg']],
    ]);

    expect($count)->toBe(3);
    expect($activity->getMedia('cover'))->toHaveCount(1);
    expect($activity->getMedia('photos'))->toHaveCount(2);
});

it('clears existing photos so re-running is idempotent', function () {
    Http::fake(['https://cdn.example/*' => Http::response(fakeJpeg(), 200)]);

    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '1']);

    $photos = [
        ['unique_id' => 'a', 'urls' => ['2048' => 'https://cdn.example/a.jpg']],
        ['unique_id' => 'b', 'urls' => ['2048' => 'https://cdn.example/b.jpg']],
    ];

    app(SyncStravaPhotos::class)($activity, $photos);
    app(SyncStravaPhotos::class)($activity, $photos);

    expect($activity->getMedia('cover'))->toHaveCount(1);
    expect($activity->getMedia('photos'))->toHaveCount(1);
});

it('backfills photos only for activities that have them on strava', function () {
    $withPhotos = Activity::factory()->create(['source' => 'strava', 'source_id' => '777', 'name' => 'Sunset run']);
    $withoutPhotos = Activity::factory()->create(['source' => 'strava', 'source_id' => '888']);

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::sequence()
            ->push([
                ['id' => 777, 'total_photo_count' => 2],
                ['id' => 888, 'total_photo_count' => 0],
            ])
            ->push([]),
        '*/activities/777/photos*' => Http::response([
            ['unique_id' => 'a', 'urls' => ['2048' => 'https://cdn.example/a.jpg']],
            ['unique_id' => 'b', 'urls' => ['2048' => 'https://cdn.example/b.jpg']],
        ]),
        'https://cdn.example/*' => Http::response(fakeJpeg(), 200),
    ]);

    $this->artisan('strava:photos')->assertSuccessful();

    expect($withPhotos->refresh()->getMedia('cover'))->toHaveCount(1);
    expect($withPhotos->getMedia('photos'))->toHaveCount(1);
    expect($withoutPhotos->refresh()->getMedia('cover'))->toHaveCount(0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/activities/888/photos'));
});

it('skips activities that already have photos unless forced', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '777']);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('existing.jpg')->toMediaCollection('cover');

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::sequence()
            ->push([['id' => 777, 'total_photo_count' => 1]])
            ->push([]),
        '*/activities/777/photos*' => Http::response([
            ['unique_id' => 'new', 'urls' => ['2048' => 'https://cdn.example/new.jpg']],
        ]),
        'https://cdn.example/*' => Http::response(fakeJpeg(), 200),
    ]);

    $this->artisan('strava:photos')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/photos'));
});

it('includes the photos gallery in the activity feed card', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '777']);

    expect($activity->card()['meta']['photos'])->toBe([]);

    $activity->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    $photos = $activity->refresh()->card()['meta']['photos'];

    expect($photos)->toHaveCount(1);
    expect($photos[0])->toHaveKeys(['src', 'srcset', 'full']);
});

it('exposes the activity photos in the entry payload, cover first', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '777', 'occurred_at' => '2026-06-20 09:00:00']);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(fakeJpeg())->usingFileName('extra.jpg')->toMediaCollection('photos');

    $url = $activity->occurred_at->format('Y/m/d').'/'.$activity->slug();

    get("/{$url}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->has('entry.photos', 2)
        ->has('entry.photos.0.src')
        ->has('entry.photos.0.full')
    );
});

it('still stores photos when the strava summary has no start date', function () {
    // Missing start_date must leave the start null rather than fabricating "now",
    // and must not cost us the photo download.
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '999']);

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
        '*/athlete/activities*' => Http::sequence()
            ->push([
                ['id' => 999, 'total_photo_count' => 1],
                // Note: intentionally no 'start_date' key to test null handling
            ])
            ->push([]),
        '*/activities/999/photos*' => Http::response([
            ['unique_id' => 'x', 'urls' => ['2048' => 'https://cdn.example/x.jpg']],
        ]),
        'https://cdn.example/*' => Http::response(fakeJpeg(), 200),
    ]);

    $this->artisan('strava:photos')->assertSuccessful();

    expect($activity->refresh()->getMedia('cover'))->toHaveCount(1);
});

it('stores photos with coordinates interpolated from the stream', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    fakeStravaPhotos(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        ['100' => [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')]],
        ['*/streams*' => Http::response([
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ])],
    );

    $this->artisan('strava:photos')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media->getCustomProperty('latitude'))->toBe(51.1)
        ->and($media->getCustomProperty('longitude'))->toBe(-0.1);
});

it('still stores the photo when the streams request fails', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    fakeStravaPhotos(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        ['100' => [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')]],
        ['*/streams*' => Http::response([], 500)],
    );

    $this->artisan('strava:photos')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    // The photo survives a stream failure; only the marker is lost.
    expect($media)->not->toBeNull()
        ->and($media->hasCustomProperty('latitude'))->toBeFalse();
});

it('still stores the photo for an indoor activity with no latlng stream', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    fakeStravaPhotos(
        [['id' => 100, 'start_date' => '2023-10-31T21:00:00Z', 'total_photo_count' => 1]],
        ['100' => [stravaPhotoPayload('photo-a', '2023-10-31T21:00:10Z')]],
        ['*/streams*' => Http::response([
            'time' => ['data' => [0, 10, 20]],
            'distance' => ['data' => [0, 5, 9]],
        ])],
    );

    $this->artisan('strava:photos')->assertSuccessful();

    expect($activity->refresh()->getFirstMedia('cover'))->not->toBeNull();
});

// Closes a Task 4 review finding. resolveTargets() must leave `start` null when
// Strava sends no start_date, never fabricate one: CarbonImmutable::parse('')
// silently returns NOW rather than throwing. The photo's capture time is
// deliberately set to now, so a fabricated now-start would land at offset ~0,
// inside the stream, and produce coordinates. Only a genuinely null start
// produces none. An older capture time could not tell the two apart, because
// both would fall outside the stream bounds and yield no coordinates either way.
it('does not fabricate a start when the strava summary has no start date', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);

    fakeStravaPhotos(
        [['id' => 100, 'total_photo_count' => 1]],
        ['100' => [stravaPhotoPayload('photo-a', now()->toIso8601ZuluString())]],
        ['*/streams*' => Http::response([
            'time' => ['data' => [0, 10, 20]],
            'latlng' => ['data' => [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]],
        ])],
    );

    $this->artisan('strava:photos')->assertSuccessful();

    $media = $activity->refresh()->getFirstMedia('cover');

    expect($media)->not->toBeNull()
        ->and($media->hasCustomProperty('latitude'))->toBeFalse();
});
