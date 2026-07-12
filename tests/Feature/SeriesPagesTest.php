<?php

use App\Models\Media;
use App\Models\Series;
use Inertia\Testing\AssertableInertia as Assert;

it('lists shows on the tv index', function () {
    $series = Series::factory()->create(['title' => 'Severance', 'slug' => 'severance']);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesIndex')->has('series', 1));
});

it('shows a series with its episodes and stats', function () {
    $series = Series::factory()->create(['slug' => 'severance', 'meta' => ['aired_episodes' => 9]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv/severance')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesShow')->where('series.slug', 'severance')->has('stats'));
});

it('gives each grouped episode its standard entry url', function () {
    $series = Series::factory()->create(['slug' => 'severance']);
    $episode = Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv/severance')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesShow')
            ->where('seasons.0.dates.0.episodes.0.url', $episode->url())
        );
});
