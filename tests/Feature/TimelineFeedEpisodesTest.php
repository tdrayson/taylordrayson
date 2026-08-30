<?php

use App\Actions\BuildTimelineFeed;
use App\Models\Media;
use App\Models\Series;
use App\Models\TimelineEntry;
use Illuminate\Support\Facades\Storage;

it('gives every same-show same-day episode its own card linking to its own entry', function () {
    $series = Series::factory()->create(['slug' => 'severance', 'title' => 'Severance']);
    collect([1, 2, 3])->each(fn ($n) => Media::factory()->create([
        'series_id' => $series->id, 'type' => 'episode',
        'occurred_at' => "2024-03-01 2{$n}:00:00",
        'meta' => ['season' => 1, 'episode' => $n, 'show_title' => 'Severance'],
    ]));

    $entries = TimelineEntry::query()->orderBy('occurred_at')->with('timelineable')->get();
    $day = app(BuildTimelineFeed::class)->groupByDay($entries)[0];

    $urls = collect($day['items'])->pluck('url');

    expect($day['items'])->toHaveCount(3)
        ->and($urls->unique())->toHaveCount(3)
        ->and($urls->all())->toBe($entries->map->timelineable->map->url()->all());
});

it('preserves the caller order (ascending is not reversed)', function () {
    // The year/month pages feed entries oldest-first via groupsForDates(ascending: true).
    $series = Series::factory()->create(['slug' => 'severance', 'title' => 'Severance']);
    Media::factory()->create(['type' => 'film', 'title' => 'Morning Film', 'occurred_at' => '2024-05-01 08:00:00', 'meta' => ['year' => 2020]]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-05-01 10:00:00', 'meta' => ['season' => 1, 'episode' => 1, 'show_title' => 'Severance']]);
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-05-01 11:00:00', 'meta' => ['season' => 1, 'episode' => 2, 'show_title' => 'Severance']]);
    Media::factory()->create(['type' => 'film', 'title' => 'Night Film', 'occurred_at' => '2024-05-01 20:00:00', 'meta' => ['year' => 2021]]);

    $entries = TimelineEntry::query()->orderBy('occurred_at')->with('timelineable')->get();
    $day = app(BuildTimelineFeed::class)->groupByDay($entries)[0];

    expect($day['items'])->toHaveCount(4)
        ->and($day['items'][0]['title'])->toBe('Morning Film')
        ->and($day['items'][3]['title'])->toBe('Night Film');
});

it('renders a show backdrop once a day, on the first episode of the run', function () {
    Storage::fake(config('media-library.disk_name'));

    $series = Series::factory()->create(['slug' => 'severance', 'title' => 'Severance']);
    $series->addMediaFromString(file_get_contents(base_path('tests/Fixtures/pixel.webp')))
        ->usingFileName('backdrop.webp')
        ->toMediaCollection('backdrop');

    collect([1, 2, 3, 4])->each(fn ($n) => Media::factory()->create([
        'series_id' => $series->id, 'type' => 'episode',
        'occurred_at' => "2024-03-01 1{$n}:00:00",
        'meta' => ['season' => 1, 'episode' => $n, 'show_title' => 'Severance'],
    ]));

    $entries = TimelineEntry::query()->orderByInstant('desc')->with('timelineable')->get();
    $items = app(BuildTimelineFeed::class)->groupByDay($entries)[0]['items'];

    // Newest first, so the run is led by episode 4.
    expect($items)->toHaveCount(4)
        ->and($items[0]['backdrop'])->not->toBeNull()
        ->and(collect($items)->pluck('backdrop')->filter())->toHaveCount(1);
});

it('keeps a backdrop for each show watched on the same day', function () {
    Storage::fake(config('media-library.disk_name'));

    $bytes = file_get_contents(base_path('tests/Fixtures/pixel.webp'));

    collect(['severance' => 'Severance', 'shrinking' => 'Shrinking'])
        ->each(function (string $title, string $slug) use ($bytes) {
            $series = Series::factory()->create(['slug' => $slug, 'title' => $title]);
            $series->addMediaFromString($bytes)->usingFileName("{$slug}.webp")->toMediaCollection('backdrop');

            Media::factory()->create([
                'series_id' => $series->id, 'type' => 'episode',
                'occurred_at' => '2024-03-01 20:00:00',
                'meta' => ['season' => 1, 'episode' => 1, 'show_title' => $title],
            ]);
        });

    $entries = TimelineEntry::query()->orderByInstant('desc')->with('timelineable')->get();
    $items = app(BuildTimelineFeed::class)->groupByDay($entries)[0]['items'];

    expect(collect($items)->pluck('backdrop')->filter()->unique())->toHaveCount(2);
});
