<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');
});

it('exposes latitude and longitude for a located photo', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('located.jpg')
        ->withCustomProperties(['latitude' => 51.1, 'longitude' => -0.1])
        ->toMediaCollection('cover');

    $photos = $activity->refresh()->galleryPhotos();

    expect($photos[0]['latitude'])->toBe(51.1)
        ->and($photos[0]['longitude'])->toBe(-0.1);
});

it('exposes null coordinates for an unlocated photo', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('plain.jpg')->toMediaCollection('cover');

    $photos = $activity->refresh()->galleryPhotos();

    expect($photos[0]['latitude'])->toBeNull()
        ->and($photos[0]['longitude'])->toBeNull();
});

it('keeps cover first so photo indexes stay stable for the lightbox', function () {
    $activity = Activity::factory()->create(['source' => 'strava', 'source_id' => '100']);
    $activity->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('gallery.jpg')
        ->withCustomProperties(['latitude' => 51.2, 'longitude' => -0.2])
        ->toMediaCollection('photos');

    $photos = $activity->refresh()->galleryPhotos();

    // The unlocated cover holds index 0; the located gallery photo is index 1.
    expect($photos[0]['latitude'])->toBeNull()
        ->and($photos[1]['latitude'])->toBe(51.2);
});
