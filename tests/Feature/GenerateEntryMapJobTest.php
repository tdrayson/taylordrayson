<?php

use App\Actions\GenerateFlightMap;
use App\Actions\GenerateLocationMap;
use App\Actions\GenerateStaticMap;
use App\Exceptions\MapGenerationFailed;
use App\Jobs\GenerateEntryMap;
use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Note;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
    Storage::fake('public');
});

it('draws the right kind of map for the entry it is given', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);

    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U_ulLnnqC']]);
    $checkin = Checkin::factory()->create(['latitude' => 51.31, 'longitude' => -0.06]);

    (new GenerateEntryMap($activity))->handle(...mapActions());
    (new GenerateEntryMap($checkin))->handle(...mapActions());

    // Re-read: the job's already-mapped guard caches an empty media relation on
    // the instance it was handed, which the generator's writes do not update.
    // A queued job gets a freshly deserialized model, so this only bites here.
    expect($activity->fresh()->getFirstMedia('map'))->not->toBeNull()
        ->and($activity->fresh()->getFirstMedia('map_dark'))->not->toBeNull()
        ->and($checkin->fresh()->getFirstMedia('map'))->not->toBeNull()
        ->and($checkin->fresh()->getFirstMedia('map_dark'))->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'path-'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'pin-l'));
});

/**
 * The job is dispatched on creation and swept up again by maps:generate, so the
 * same entry can reach it twice. Redrawing costs two Mapbox calls for an image
 * that is already there.
 */
it('does nothing when the entry already has a map', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);

    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U_ulLnnqC']]);
    (new GenerateEntryMap($activity))->handle(...mapActions());

    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);
    (new GenerateEntryMap($activity->fresh()))->handle(...mapActions());

    Http::assertNothingSent();
});

/**
 * The whole point of queueing: a failed Mapbox call must reach the queue as a
 * failure so it is retried, rather than being swallowed the way it was when
 * this ran inline.
 */
it('lets a failure surface so the queue retries it', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('', 500)]);

    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U_ulLnnqC']]);

    expect(fn () => (new GenerateEntryMap($activity))->handle(...mapActions()))
        ->toThrow(MapGenerationFailed::class);
});

it('ignores an entry type that has no map', function () {
    $note = Note::factory()->create();

    (new GenerateEntryMap($note))->handle(...mapActions());
})->throwsNoExceptions();

/**
 * @return array{GenerateLocationMap, GenerateStaticMap, GenerateFlightMap}
 */
function mapActions(): array
{
    return [
        app(GenerateLocationMap::class),
        app(GenerateStaticMap::class),
        app(GenerateFlightMap::class),
    ];
}
