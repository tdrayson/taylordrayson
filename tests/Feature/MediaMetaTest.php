<?php

use App\Data\MediaMeta;
use App\Data\SeriesMeta;
use App\Models\Media;
use App\Models\Series;

it('round-trips keys it does not name, rather than dropping them on save', function () {
    // `isbn` and the nested `plex` ids are real stored keys that no writer in
    // the app sets and no field on the DTO names. A strict DTO would delete
    // them the first time the row was re-saved.
    $book = Media::factory()->create(['type' => 'book', 'meta' => [
        'author' => 'Pat Barker',
        'isbn' => '9780241983201',
        'ids' => ['trakt' => 5, 'plex' => ['guid' => 'abc']],
    ]]);

    $book->fresh()->touch();

    expect($book->fresh()->meta->toArray())->toBe([
        'author' => 'Pat Barker',
        'ids' => ['trakt' => 5, 'plex' => ['guid' => 'abc']],
        'isbn' => '9780241983201',
    ]);
});

it('does not sprout keys the row never had', function () {
    // A film has no season/episode. Storing null for every unset field would
    // rewrite every film's meta with a dozen empty episode keys.
    $film = Media::factory()->create(['type' => 'film', 'meta' => ['year' => 2026]]);

    $film->fresh()->touch();

    expect($film->fresh()->meta->toArray())->toBe(['year' => 2026]);
});

it('reads an empty meta column as an empty object, never null', function () {
    // So callers can say `$media->meta->year` without first proving meta exists.
    $media = Media::factory()->create(['type' => 'film', 'meta' => []]);

    expect($media->fresh()->meta)->toBeInstanceOf(MediaMeta::class)
        ->and($media->fresh()->meta->year)->toBeNull()
        ->and($media->fresh()->meta->ids->slug)->toBeNull()
        ->and($media->fresh()->meta->tmdb->genres)->toBe([]);
});

it('raises on a mistyped field instead of quietly reading null', function () {
    // The point of the whole exercise: `meta['show_titel']` used to be null,
    // indistinguishable from a show with no title.
    $episode = Media::factory()->create(['type' => 'episode', 'meta' => ['show_title' => 'Ted Lasso']]);

    expect(fn (): ?string => $episode->meta->showTitel)->toThrow(ErrorException::class);
});

it('keeps a whole-numbered rating an integer so the sync does not re-save every run', function () {
    // Stored as 8.0 it would never match the 8 Trakt sends back, and every
    // show would be written again on every sync.
    $series = Series::factory()->create(['meta' => ['rating' => 8]]);

    expect($series->fresh()->meta->rating)->toBe(8)
        ->and($series->fresh()->meta->toArray()['rating'])->toBe(8);
});

it('hydrates the season list into SeasonSummary objects and stores TMDB spelling back', function () {
    // The page reads camelCase, the column holds TMDB's snake_case, and
    // SeriesMeta is the only thing that knows both.
    $series = Series::factory()->create(['meta' => ['season_list' => [
        ['number' => 1, 'name' => 'Season 1', 'episode_count' => 6, 'air_date' => '2019-05-31'],
    ]]]);

    $fresh = $series->fresh();

    expect($fresh->meta->seasonList[0]->episodeCount)->toBe(6)
        ->and($fresh->meta->seasonList[0]->airDate)->toBe('2019-05-31')
        ->and($fresh->meta->toArray()['season_list'][0])->toBe([
            'number' => 1,
            'name' => 'Season 1',
            'episode_count' => 6,
            'air_date' => '2019-05-31',
        ]);
});

it('merges without restating the rest of the bag', function () {
    $series = Series::factory()->create(['meta' => ['aired_episodes' => 12, 'seasons' => 2]]);

    $series->meta = $series->meta->merge(['rating' => 9]);
    $series->save();

    expect($series->fresh()->meta)->toBeInstanceOf(SeriesMeta::class)
        ->and($series->fresh()->meta->rating)->toBe(9)
        ->and($series->fresh()->meta->airedEpisodes)->toBe(12)
        ->and($series->fresh()->meta->seasons)->toBe(2);
});
