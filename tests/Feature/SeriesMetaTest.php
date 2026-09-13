<?php

use App\Data\SeriesMeta;
use App\Models\Series;

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
