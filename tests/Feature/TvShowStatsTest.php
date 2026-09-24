<?php

use App\Data\TvShowStats;
use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Queries\TvShowWatchStats;

/** The stats for a show, as the show page assembles them. */
function watchStatsFor(TvShow $tvShow): TvShowStats
{
    return (new TvShowWatchStats)($tvShow->load('episodes'));
}

it('computes distinct-episode progress, clamped and rewatch-proof', function () {
    $tvShow = TvShow::factory()->create(['meta' => ['aired_episodes' => 10]]);
    // Watch episode 1 twice, episode 2 once => 2 distinct of 10 => 20%.
    TvEpisode::factory()->count(2)->create(['tv_show_id' => $tvShow->id, 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'meta' => ['season' => 1, 'episode' => 2, 'runtime' => 50]]);

    $stats = watchStatsFor($tvShow);

    expect($stats->episodesWatched)->toBe(2)
        ->and($stats->progress)->toBe(20)
        // Runtime counts every watch, unlike progress: 3 x 50 minutes.
        ->and($stats->totalHours)->toBe(3.0);
});

it('summarises the watch span from first to last watch', function () {
    $tvShow = TvShow::factory()->create();
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'occurred_at' => '2024-09-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    $span = watchStatsFor($tvShow)->watchSpan;

    expect($span)->toContain('months')
        ->and($span)->not->toStartWith('over ');
});

it('clamps progress to 100 when distinct watched episodes exceed the aired count', function () {
    $tvShow = TvShow::factory()->create(['meta' => ['aired_episodes' => 2]]);
    foreach ([1, 2, 3] as $episode) {
        TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'meta' => ['season' => 1, 'episode' => $episode]]);
    }

    $stats = watchStatsFor($tvShow);

    expect($stats->episodesWatched)->toBe(3)
        ->and($stats->progress)->toBe(100);
});

it('returns null progress when aired_episodes is missing or zero', function () {
    // Null rather than 0%, so the bar is hidden rather than drawn at a figure
    // we cannot actually calculate.
    expect(TvShowWatchStats::progressFor(null, 4))->toBeNull()
        ->and(TvShowWatchStats::progressFor(0, 4))->toBeNull()
        ->and(TvShowWatchStats::progressFor(10, 2))->toBe(20);
});

it('describes a single-day watch span as "in a single day"', function () {
    $tvShow = TvShow::factory()->create();
    // Same calendar day, different times: a one-evening binge still reads as a single day.
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'occurred_at' => '2024-01-01 22:30:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    expect(watchStatsFor($tvShow)->watchSpan)->toBe('in a single day');
});
