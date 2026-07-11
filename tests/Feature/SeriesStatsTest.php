<?php

use App\Models\Media;
use App\Models\Series;

it('computes distinct-episode progress, clamped and rewatch-proof', function () {
    $series = Series::factory()->create(['meta' => ['aired_episodes' => 10]]);
    // Watch episode 1 twice, episode 2 once => 2 distinct of 10 => 20%.
    Media::factory()->count(2)->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 2, 'runtime' => 50]]);

    expect($series->watchedEpisodeCount())->toBe(2)
        ->and($series->progress())->toBe(20)
        ->and($series->totalRuntimeMinutes())->toBe(150); // 3 watches x 50
});

it('summarises the watch span from first to last watch', function () {
    $series = Series::factory()->create();
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-09-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    expect($series->watchSpan())->toStartWith('over ')
        ->and($series->watchSpan())->toContain('months');
});

it('clamps progress to 100 when distinct watched episodes exceed the aired count', function () {
    $series = Series::factory()->create(['meta' => ['aired_episodes' => 2]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 2]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 3]]);

    expect($series->watchedEpisodeCount())->toBe(3)
        ->and($series->progress())->toBe(100);
});

it('returns null progress when aired_episodes is missing or zero', function () {
    $missing = Series::factory()->create(['meta' => []]);
    Media::factory()->create(['series_id' => $missing->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    $zero = Series::factory()->create(['meta' => ['aired_episodes' => 0]]);
    Media::factory()->create(['series_id' => $zero->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    expect($missing->progress())->toBeNull()
        ->and($zero->progress())->toBeNull();
});

it('describes a single-day watch span as "in a single day"', function () {
    $series = Series::factory()->create();
    // Same calendar day, different times: a one-evening binge still reads as a single day.
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 22:30:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    expect($series->watchSpan())->toBe('in a single day');
});
