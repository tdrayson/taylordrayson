<?php

use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Facades\Storage;

it('links episodes to a series', function () {
    $series = Series::factory()->create(['title' => 'The Good Doctor']);
    $episode = Media::factory()->create(['type' => 'episode', 'series_id' => $series->id]);

    expect($series->episodes)->toHaveCount(1)
        ->and($episode->series->is($series))->toBeTrue();
});

it('generates a base slug, then disambiguates by year, then by suffix', function () {
    $taken = [];
    // Regular closure with capture-by-reference: an arrow fn would snapshot
    // $taken at creation time and never see the appends below.
    $exists = function (string $slug) use (&$taken): bool {
        return in_array($slug, $taken, true);
    };

    $a = Series::slugFor('The Office', 2005, $exists);
    $taken[] = $a;
    $b = Series::slugFor('The Office', 2001, $exists);
    $taken[] = $b;
    $c = Series::slugFor('The Office', 2001, $exists); // same name AND year

    expect($a)->toBe('the-office')
        ->and($b)->toBe('the-office-2001')
        ->and($c)->toBe('the-office-2001-2');
});

// Each collection is exercised in its own test (rather than two collections
// per test) because Spatie Media Library's singleFile enforcement only
// re-checks the collection touched by the *first* toMediaCollection() call
// of the request; a second, unrelated collection touched afterwards in the
// same test is left untrimmed regardless of registration. That's a library
// quirk unrelated to whether our collections are registered, reproducible
// even on the pre-existing `cover`/`map` collections, so isolating each
// collection per test keeps the assertion meaningful.
it('stores a single-file backdrop image on a series', function () {
    Storage::fake(config('media-library.disk_name'));

    $series = Series::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    // Add twice: only a registered singleFile() collection replaces the
    // first upload instead of accumulating both.
    $series->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('backdrop');
    $series->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('backdrop');

    expect($series->getFirstMedia('backdrop'))->not->toBeNull()
        ->and($series->getMedia('backdrop'))->toHaveCount(1);
});

it('stores a single-file logo image on a series', function () {
    Storage::fake(config('media-library.disk_name'));

    $series = Series::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    $series->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('logo');
    $series->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('logo');

    expect($series->getFirstMedia('logo'))->not->toBeNull()
        ->and($series->getMedia('logo'))->toHaveCount(1);
});

it('stores a single-file backdrop image on a film media entry', function () {
    Storage::fake(config('media-library.disk_name'));

    $media = Media::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    $media->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('backdrop');
    $media->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('backdrop');

    expect($media->getFirstMedia('backdrop'))->not->toBeNull()
        ->and($media->getMedia('backdrop'))->toHaveCount(1);
});

it('stores a single-file logo image on a film media entry', function () {
    Storage::fake(config('media-library.disk_name'));

    $media = Media::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    $media->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('logo');
    $media->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('logo');

    expect($media->getFirstMedia('logo'))->not->toBeNull()
        ->and($media->getMedia('logo'))->toHaveCount(1);
});
