<?php

use App\Models\Media;
use App\Models\Series;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('lists shows on the tv index', function () {
    $series = Series::factory()->create(['title' => 'Severance', 'slug' => 'severance']);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesIndex')->has('series', 1));
});

it('orders the tv index by most recently watched episode first', function () {
    $older = Series::factory()->create(['title' => 'Older Show', 'slug' => 'older-show']);
    Media::factory()->create(['series_id' => $older->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $newer = Series::factory()->create(['title' => 'Newer Show', 'slug' => 'newer-show']);
    Media::factory()->create(['series_id' => $newer->id, 'type' => 'episode', 'occurred_at' => '2024-06-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesIndex')
            ->has('series', 2)
            ->where('series.0.slug', 'newer-show')
            ->where('series.1.slug', 'older-show')
        );
});

it('reports distinct-episode progress on the tv index, clamped and rewatch-proof', function () {
    // Most recently watched: rewatching episode 1 shouldn't inflate distinct progress (2 of 10 => 20%).
    $rewatched = Series::factory()->create(['slug' => 'rewatched-show', 'meta' => ['aired_episodes' => 10]]);
    Media::factory()->create(['series_id' => $rewatched->id, 'type' => 'episode', 'occurred_at' => '2024-03-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $rewatched->id, 'type' => 'episode', 'occurred_at' => '2024-03-02 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $rewatched->id, 'type' => 'episode', 'occurred_at' => '2024-03-03 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);

    // Watching more episodes than are "aired" (e.g. metadata lag) clamps to 100 rather than overshooting.
    $clamped = Series::factory()->create(['slug' => 'clamped-show', 'meta' => ['aired_episodes' => 2]]);
    Media::factory()->create(['series_id' => $clamped->id, 'type' => 'episode', 'occurred_at' => '2024-02-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['series_id' => $clamped->id, 'type' => 'episode', 'occurred_at' => '2024-02-02 20:00:00', 'meta' => ['season' => 1, 'episode' => 2]]);
    Media::factory()->create(['series_id' => $clamped->id, 'type' => 'episode', 'occurred_at' => '2024-02-03 20:00:00', 'meta' => ['season' => 1, 'episode' => 3]]);

    // No aired_episodes metadata yet => progress stays null rather than dividing by zero/missing.
    $unknown = Series::factory()->create(['slug' => 'unknown-aired-show', 'meta' => []]);
    Media::factory()->create(['series_id' => $unknown->id, 'type' => 'episode', 'occurred_at' => '2024-01-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);

    $this->get('/media/tv')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesIndex')
            ->has('series', 3)
            ->where('series.0.slug', 'rewatched-show')
            ->where('series.0.progress', 20)
            ->where('series.1.slug', 'clamped-show')
            ->where('series.1.progress', 100)
            ->where('series.2.slug', 'unknown-aired-show')
            ->where('series.2.progress', null)
        );
});

it('resolves a poster on the tv index for a series with a cover image', function () {
    Storage::fake(config('media-library.disk_name'));

    $series = Series::factory()->create(['slug' => 'severance']);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'meta' => ['season' => 1, 'episode' => 1]]);

    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));
    $series->addMediaFromString($bytes)->usingFileName('c.webp')->toMediaCollection('cover');

    $this->get('/media/tv')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media/SeriesIndex')
            ->has('series', 1)
            ->where('series.0.slug', 'severance')
            ->where('series.0.poster', fn (?string $poster): bool => $poster !== null)
        );
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
