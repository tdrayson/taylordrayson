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

it('filters to a season and to a single episode across all its watches', function () {
    $series = Series::factory()->create(['slug' => 'the-good-doctor']);
    Media::factory()->count(2)->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 6, 'episode' => 8]]); // watched twice
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 7, 'episode' => 1]]);

    $this->get('/media/tv/the-good-doctor/season-6')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesSeason'));
    $this->get('/media/tv/the-good-doctor/season-6/episode-8')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesEpisode')->has('watches', 2));
});
