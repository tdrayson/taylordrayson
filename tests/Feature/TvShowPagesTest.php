<?php

use App\Models\TvEpisode;
use App\Models\TvShow;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('lists shows on the tv index', function () {
    $tvShow = TvShow::factory()->create(['title' => 'Severance', 'slug' => 'severance']);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/tv-shows')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TvShows/Index')->has('shows', 1));
});

it('orders the tv index by most recently watched episode first', function () {
    $older = TvShow::factory()->create(['title' => 'Older Show', 'slug' => 'older-show']);
    TvEpisode::factory()->create(['tv_show_id' => $older->id, 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $newer = TvShow::factory()->create(['title' => 'Newer Show', 'slug' => 'newer-show']);
    TvEpisode::factory()->create(['tv_show_id' => $newer->id, 'occurred_at' => '2024-06-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/tv-shows')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TvShows/Index')
            ->has('shows', 2)
            ->where('shows.0.slug', 'newer-show')
            ->where('shows.1.slug', 'older-show')
        );
});

it('reports distinct-episode progress on the tv index, clamped and rewatch-proof', function () {
    // Most recently watched: rewatching episode 1 shouldn't inflate distinct progress (2 of 10 => 20%).
    $rewatched = TvShow::factory()->create(['slug' => 'rewatched-show', 'meta' => ['aired_episodes' => 10]]);
    TvEpisode::factory()->create(['tv_show_id' => $rewatched->id, 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    TvEpisode::factory()->create(['tv_show_id' => $rewatched->id, 'occurred_at' => '2024-03-02 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    TvEpisode::factory()->create(['tv_show_id' => $rewatched->id, 'occurred_at' => '2024-03-03 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    // Watching more episodes than are "aired" (e.g. metadata lag) clamps to 100 rather than overshooting.
    $clamped = TvShow::factory()->create(['slug' => 'clamped-show', 'meta' => ['aired_episodes' => 2]]);
    TvEpisode::factory()->create(['tv_show_id' => $clamped->id, 'occurred_at' => '2024-02-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    TvEpisode::factory()->create(['tv_show_id' => $clamped->id, 'occurred_at' => '2024-02-02 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);
    TvEpisode::factory()->create(['tv_show_id' => $clamped->id, 'occurred_at' => '2024-02-03 20:00:00', 'meta' => ['season' => 1, 'episode' => 3]]);

    // No aired_episodes metadata yet => progress stays null rather than dividing by zero/missing.
    $unknown = TvShow::factory()->create(['slug' => 'unknown-aired-show', 'meta' => []]);
    TvEpisode::factory()->create(['tv_show_id' => $unknown->id, 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/tv-shows')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TvShows/Index')
            ->has('shows', 3)
            ->where('shows.0.slug', 'rewatched-show')
            ->where('shows.0.progress', 20)
            ->where('shows.1.slug', 'clamped-show')
            ->where('shows.1.progress', 100)
            ->where('shows.2.slug', 'unknown-aired-show')
            ->where('shows.2.progress', null)
        );
});

it('resolves a poster on the tv index for a show with a cover image', function () {
    Storage::fake(config('media-library.disk_name'));

    $tvShow = TvShow::factory()->create(['slug' => 'severance']);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'meta' => ['season' => 1, 'episode' => 1]]);

    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));
    $tvShow->addMediaFromString($bytes)->usingFileName('c.webp')->toMediaCollection('cover');

    $this->get('/tv-shows')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TvShows/Index')
            ->has('shows', 1)
            ->where('shows.0.slug', 'severance')
            ->where('shows.0.poster', fn (?string $poster): bool => $poster !== null)
        );
});

it('shows a tv show with its episodes and stats', function () {
    $tvShow = TvShow::factory()->create(['slug' => 'severance', 'meta' => ['aired_episodes' => 9]]);
    TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/tv-shows/severance')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TvShows/Show')->where('show.slug', 'severance')->has('stats'));
});

it('gives each grouped episode its standard entry url', function () {
    $tvShow = TvShow::factory()->create(['slug' => 'severance']);
    $episode = TvEpisode::factory()->create(['tv_show_id' => $tvShow->id, 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/tv-shows/severance')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TvShows/Show')
            ->where('seasons.0.dates.0.episodes.0.url', $episode->url())
        );
});
