<?php

use App\Actions\SyncStravaPhotos;
use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

/** A real JPEG of the given size, so the media library can process it. */
function fakeJpeg(int $width = 800, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);
    Cache::flush();
    Storage::fake('public');
});

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
