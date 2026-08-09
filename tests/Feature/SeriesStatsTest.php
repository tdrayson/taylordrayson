<?php

use App\Data\SeriesStats;
use App\Models\Media;
use App\Models\Series;
use App\Queries\SeriesWatchStats;

/** The stats for a show, as the show page assembles them. */
function watchStatsFor(Series $series): SeriesStats
{
    return (new SeriesWatchStats)($series->load('episodes'));
}

it('computes distinct-episode progress, clamped and rewatch-proof', function () {
    $series = Series::factory()->create(['meta' => ['aired_episodes' => 10]]);
    // Watch episode 1 twice, episode 2 once => 2 distinct of 10 => 20%.
    Media::factory()->count(2)->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 2, 'runtime' => 50]]);

    $stats = watchStatsFor($series);

    expect($stats->episodesWatched)->toBe(2)
        ->and($stats->progress)->toBe(20)
        // Runtime counts every watch, unlike progress: 3 x 50 minutes.
        ->and($stats->totalHours)->toBe(3.0);
});

it('summarises the watch span from first to last watch', function () {
    $series = Series::factory()->create();
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-09-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    $span = watchStatsFor($series)->watchSpan;

    expect($span)->toContain('months')
        ->and($span)->not->toStartWith('over ');
});

it('clamps progress to 100 when distinct watched episodes exceed the aired count', function () {
    $series = Series::factory()->create(['meta' => ['aired_episodes' => 2]]);
    foreach ([1, 2, 3] as $episode) {
        Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => $episode]]);
    }

    $stats = watchStatsFor($series);

    expect($stats->episodesWatched)->toBe(3)
        ->and($stats->progress)->toBe(100);
});

it('returns null progress when aired_episodes is missing or zero', function () {
    // Null rather than 0%, so the bar is hidden rather than drawn at a figure
    // we cannot actually calculate.
    expect(SeriesWatchStats::progressFor(null, 4))->toBeNull()
        ->and(SeriesWatchStats::progressFor(0, 4))->toBeNull()
        ->and(SeriesWatchStats::progressFor(10, 2))->toBe(20);
});

it('describes a single-day watch span as "in a single day"', function () {
    $series = Series::factory()->create();
    // Same calendar day, different times: a one-evening binge still reads as a single day.
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 22:30:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    expect(watchStatsFor($series)->watchSpan)->toBe('in a single day');
});
