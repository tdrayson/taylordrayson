<?php

use App\Models\Episode;
use App\Models\Series;

it('renders the series page with episode rows grouped by date', function () {
    $series = Series::factory()->create(['title' => 'Severance', 'slug' => 'severance', 'meta' => ['aired_episodes' => 9, 'seasons' => 1]]);
    Episode::factory()->create(['series_id' => $series->id, 'title' => 'Good News About Hell', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);

    $page = visit('/tv/severance');

    $page->assertSee('Severance')
        ->assertPresent('[data-testid="series-stats"]')
        ->assertPresent('[data-testid="episode-row"]');
});

it('renders the tv index as a poster grid', function () {
    $series = Series::factory()->create(['slug' => 'severance']);
    Episode::factory()->create(['series_id' => $series->id, 'meta' => ['season' => 1, 'episode' => 1]]);

    visit('/tv')->assertPresent('[data-testid="poster-card"]');
});

it('renders the backdrop hero and season overview from TMDB enrichment', function () {
    $series = Series::factory()->create([
        'title' => 'Severance',
        'slug' => 'severance',
        'meta' => [
            'aired_episodes' => 9,
            'seasons' => 2,
            'season_list' => [
                ['number' => 1, 'name' => 'Season 1', 'episode_count' => 9, 'air_date' => '2022-02-18'],
                ['number' => 2, 'name' => 'Season 2', 'episode_count' => 10, 'air_date' => '2025-01-17'],
            ],
        ],
    ]);
    $series->addMediaFromString(file_get_contents(base_path('tests/Fixtures/pixel.webp')))
        ->usingFileName('backdrop.webp')
        ->toMediaCollection('backdrop');
    Episode::factory()->create(['series_id' => $series->id, 'title' => 'Good News About Hell', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);

    $page = visit('/tv/severance');

    $page->assertPresent('[data-testid="entry-hero"]')
        ->assertPresent('[data-testid="season-overview"]');
});
