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

    expect($series->watchSpan())->toContain('months');
});
