<?php

use App\Models\TvEpisode;
use App\Models\TvShow;

it('renders the tv show page with episode rows grouped by date', function () {
    $tvShow = TvShow::factory()->create(['title' => 'Severance', 'slug' => 'severance', 'meta' => ['aired_episodes' => 9, 'seasons' => 1]]);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'title' => 'Good News About Hell', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);

    $page = visit('/tv-shows/severance');

    $page->assertSee('Severance')
        ->assertPresent('[data-testid="tv-show-stats"]')
        ->assertPresent('[data-testid="episode-row"]');
});

it('renders the tv index as a poster grid', function () {
    $tvShow = TvShow::factory()->create(['slug' => 'severance']);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'meta' => ['season' => 1, 'episode' => 1]]);

    visit('/tv-shows')->assertPresent('[data-testid="poster-card"]');
});

it('renders the backdrop hero and season overview from TMDB enrichment', function () {
    $tvShow = TvShow::factory()->create([
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
    $tvShow->addMediaFromString(file_get_contents(base_path('tests/Fixtures/pixel.webp')))
        ->usingFileName('backdrop.webp')
        ->toMediaCollection('backdrop');
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'title' => 'Good News About Hell', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'runtime' => 50]]);

    $page = visit('/tv-shows/severance');

    $page->assertPresent('[data-testid="entry-hero"]')
        ->assertPresent('[data-testid="season-overview"]');
});
