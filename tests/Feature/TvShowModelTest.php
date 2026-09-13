<?php

use App\Models\Film;
use App\Models\TvEpisode;
use App\Models\TvShow;
use Illuminate\Support\Facades\Storage;

it('links episodes to a show', function () {
    $tvShow = TvShow::factory()->create(['title' => 'The Good Doctor']);
    $episode = TvEpisode::factory()->create(['tv_show_id' => $tvShow->id]);

    expect($tvShow->episodes)->toHaveCount(1)
        ->and($episode->tvShow->is($tvShow))->toBeTrue();
});

it('generates a base slug, then disambiguates by year, then by suffix', function () {
    $taken = [];
    // Regular closure with capture-by-reference: an arrow fn would snapshot
    // $taken at creation time and never see the appends below.
    $exists = function (string $slug) use (&$taken): bool {
        return in_array($slug, $taken, true);
    };

    $a = TvShow::slugFor('The Office', 2005, $exists);
    $taken[] = $a;
    $b = TvShow::slugFor('The Office', 2001, $exists);
    $taken[] = $b;
    $c = TvShow::slugFor('The Office', 2001, $exists); // same name AND year

    expect($a)->toBe('the-office')
        ->and($b)->toBe('the-office-2001')
        ->and($c)->toBe('the-office-2001-2');
});

// One collection per test: Media Library's singleFile enforcement only re-checks
// the collection touched by the first toMediaCollection() call of the request, so
// a second one in the same test is left untrimmed regardless of registration.
it('stores a single-file backdrop image on a show', function () {
    Storage::fake(config('media-library.disk_name'));

    $tvShow = TvShow::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    // Add twice: only a registered singleFile() collection replaces the
    // first upload instead of accumulating both.
    $tvShow->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('backdrop');
    $tvShow->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('backdrop');

    expect($tvShow->getFirstMedia('backdrop'))->not->toBeNull()
        ->and($tvShow->getMedia('backdrop'))->toHaveCount(1);
});

it('stores a single-file logo image on a show', function () {
    Storage::fake(config('media-library.disk_name'));

    $tvShow = TvShow::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    $tvShow->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('logo');
    $tvShow->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('logo');

    expect($tvShow->getFirstMedia('logo'))->not->toBeNull()
        ->and($tvShow->getMedia('logo'))->toHaveCount(1);
});

it('stores a single-file backdrop image on a film', function () {
    Storage::fake(config('media-library.disk_name'));

    $film = Film::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    $film->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('backdrop');
    $film->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('backdrop');

    expect($film->getFirstMedia('backdrop'))->not->toBeNull()
        ->and($film->getMedia('backdrop'))->toHaveCount(1);
});

it('stores a single-file logo image on a film', function () {
    Storage::fake(config('media-library.disk_name'));

    $film = Film::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    $film->addMediaFromString($bytes)->usingFileName('a.webp')->toMediaCollection('logo');
    $film->addMediaFromString($bytes)->usingFileName('b.webp')->toMediaCollection('logo');

    expect($film->getFirstMedia('logo'))->not->toBeNull()
        ->and($film->getMedia('logo'))->toHaveCount(1);
});
