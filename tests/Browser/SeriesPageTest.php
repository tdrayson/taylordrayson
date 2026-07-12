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

it('renders the backdrop hero, rating badges, and season overview from TMDB/OMDB enrichment', function () {
    $series = Series::factory()->create([
        'title' => 'Severance',
        'slug' => 'severance',
        'meta' => [
            'aired_episodes' => 9,
            'seasons' => 2,
            'ratings' => [
                'imdb' => '8.7',
                'rotten_tomatoes' => '97%',
                'certification' => 'TV-MA',
            ],
            'season_list' => [
                ['number' => 1, 'name' => 'Season 1', 'episode_count' => 9, 'air_date' => '2022-02-18'],
                ['number' => 2, 'name' => 'Season 2', 'episode_count' => 10, 'air_date' => '2025-01-17'],
            ],
        ],
    ]);
    $series->addMediaFromString(file_get_contents(base_path('tests/Fixtures/pixel.webp')))
        ->usingFileName('backdrop.webp')
        ->toMediaCollection('backdrop');
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'title' => 'Good News About Hell', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);

    $page = visit('/media/tv/severance');

    $page->assertPresent('[data-testid="series-backdrop"]')
        ->assertPresent('[data-testid="rating-badge"]')
        ->assertPresent('[data-testid="season-overview"]');
});
