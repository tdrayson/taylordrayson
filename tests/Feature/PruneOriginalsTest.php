<?php

use App\Models\Activity;
use App\Models\Podcast;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('deletes the original but keeps the optimised copy', function () {
    $activity = activityWithPhoto(noisyJpeg());
    $media = $activity->getFirstMedia('photos');
    $original = $media->getPath();
    $optimised = $media->getPath('full');

    expect(is_file($original))->toBeTrue();

    $this->artisan('media:prune-originals --force')->assertSuccessful();

    expect(is_file($original))->toBeFalse()
        ->and(is_file($optimised))->toBeTrue()
        // The row survives: the conversions hang off it.
        ->and($media->fresh()->getCustomProperty('original_pruned'))->toBeTrue();
});

it('deletes nothing without --force', function () {
    $activity = activityWithPhoto(noisyJpeg());
    $original = $activity->getFirstMedia('photos')->getPath();

    $this->artisan('media:prune-originals')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(is_file($original))->toBeTrue();
});

/**
 * The guard that matters most. These types are deliberately never converted,
 * so their original is the only copy: pruning one would leave nothing at all
 * to serve.
 */
it('never deletes an original that has no optimised copy', function (string $bytes, string $name) {
    $activity = activityWithPhoto($bytes, $name);
    $media = $activity->getFirstMedia('photos');

    expect($media->hasGeneratedConversion('full'))->toBeFalse();

    $this->artisan('media:prune-originals --force')->assertSuccessful();

    expect(is_file($media->getPath()))->toBeTrue();
})->with([
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>', 'logo.svg'],
    'gif' => [base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'loop.gif'],
]);

it('never touches a mirrored podcast episode', function () {
    $podcast = Podcast::factory()->create(['season_number' => 7, 'episode_number' => 1]);
    $podcast->addMediaFromString('audio-bytes')->usingFileName('ep.mp3')->toMediaCollection('audio');
    $audio = $podcast->fresh()->getFirstMedia('audio');

    $this->artisan('media:prune-originals --force')->assertSuccessful();

    // There is no conversion of an MP3, so the original is the only copy.
    expect(is_file($audio->getPath()))->toBeTrue();
});

it('does not prune twice', function () {
    activityWithPhoto(noisyJpeg());

    $this->artisan('media:prune-originals --force')->assertSuccessful();
    $this->artisan('media:prune-originals --force')
        ->expectsOutputToContain('Nothing to prune')
        ->assertSuccessful();
});

it('leaves the entry rendering after a prune', function () {
    $activity = activityWithPhoto(noisyJpeg());

    $this->artisan('media:prune-originals --force')->assertSuccessful();

    // The gallery must not point at the file that was just deleted.
    $photos = $activity->fresh()->galleryPhotos();

    expect($photos)->toHaveCount(1)
        ->and($photos[0]['full'])->toContain('-full.webp');
});

it('can be limited to one collection', function () {
    $activity = Activity::factory()->create();
    $activity->addMediaFromString(noisyJpeg())->usingFileName('p.jpg')->toMediaCollection('photos');
    $activity->addMediaFromString(noisyJpeg(400, 400))->usingFileName('m.jpg')->toMediaCollection('map');
    $activity = $activity->fresh();

    $this->artisan('media:prune-originals --collection=map --force')->assertSuccessful();

    expect(is_file($activity->getFirstMedia('map')->getPath()))->toBeFalse()
        ->and(is_file($activity->getFirstMedia('photos')->getPath()))->toBeTrue();
});
