<?php

use App\Models\Media;
use App\Models\Series;

it('renders the series page with episode rows grouped by date', function () {
    $series = Series::factory()->create(['title' => 'Severance', 'slug' => 'severance', 'meta' => ['aired_episodes' => 9, 'seasons' => 1]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'title' => 'Good News About Hell', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);

    $page = visit('/media/tv/severance');

    $page->assertSee('Severance')
        ->assertPresent('[data-testid="series-stats"]')
        ->assertPresent('[data-testid="episode-row"]');
});

it('renders the tv index as a poster grid', function () {
    $series = Series::factory()->create(['slug' => 'severance']);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    visit('/media/tv')->assertPresent('[data-testid="poster-card"]');
});
