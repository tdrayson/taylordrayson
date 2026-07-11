<?php

use App\Actions\BuildTimelineFeed;
use App\Models\Media;
use App\Models\Series;
use App\Models\TimelineEntry;

it('collapses a same-show same-day binge into one card', function () {
    $series = Series::factory()->create(['slug' => 'severance', 'title' => 'Severance']);
    collect([1, 2, 3])->each(fn ($n) => Media::factory()->create([
        'series_id' => $series->id, 'type' => 'episode',
        'occurred_at' => "2024-03-01 2{$n}:00:00",
        'meta' => ['season' => 1, 'episode' => $n, 'show_title' => 'Severance'],
    ]));

    $feed = app(BuildTimelineFeed::class);
    $day = $feed->groupByDay(TimelineEntry::query()->with('timelineable')->get())[0];

    $severance = collect($day['items'])->firstWhere('title', 'Severance');
    expect($severance)->not->toBeNull()
        ->and($severance['count'])->toBe(3)
        ->and($severance['url'])->toBe('/media/tv/severance#watch-2024-03-01')
        ->and($day['items'])->toHaveCount(1);
});

it('leaves a single episode and a film as their own cards', function () {
    $series = Series::factory()->create();
    Media::factory()->create(['series_id' => $series->id, 'type' => 'episode', 'occurred_at' => '2024-04-01 20:00:00', 'meta' => ['season' => 1, 'episode' => 1]]);
    Media::factory()->create(['type' => 'film', 'occurred_at' => '2024-04-01 22:00:00', 'meta' => ['year' => 2021]]);

    $day = app(BuildTimelineFeed::class)->groupByDay(TimelineEntry::query()->with('timelineable')->get())[0];

    expect($day['items'])->toHaveCount(2)
        ->and(collect($day['items'])->pluck('count')->filter())->toBeEmpty();
});
