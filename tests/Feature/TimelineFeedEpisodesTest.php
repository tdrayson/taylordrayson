<?php

use App\Actions\BuildTimelineFeed;
use App\Models\Media;
use App\Models\Series;
use App\Models\TimelineEntry;

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
