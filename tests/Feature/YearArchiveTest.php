<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\Flight;
use App\Models\Note;
use App\Models\Sleep;

use function Pest\Laravel\get;

it('serves real year numbers, entry count and heatmap', function () {
    Activity::factory()->create(['occurred_at' => '2025-03-10 09:00:00', 'distance' => 5000]);
    Activity::factory()->create(['occurred_at' => '2025-03-10 18:00:00', 'distance' => 7000]);
    Sleep::factory()->create(['occurred_at' => '2025-03-11 00:00:00', 'duration' => 8 * 3600]);
    Flight::factory()->create(['occurred_at' => '2025-06-01 10:00:00']);
    Article::factory()->create(['occurred_at' => '2025-07-01 12:00:00', 'published' => true]);
    Note::factory()->create(['occurred_at' => '2025-07-02 12:00:00']);

    get('/2025')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Year')
            ->where('entriesCount', 6)
            ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Activities'))
            ->where('stats', fn ($stats) => collect($stats)->pluck('label')->contains('Written'))
            ->where('heatmap.2025-03-10', 2)
            ->where('heatmap.2025-06-01', 1));
});

it('excludes other years from the aggregates', function () {
    Activity::factory()->create(['occurred_at' => '2024-12-31 09:00:00']);

    get('/2025')->assertInertia(fn ($page) => $page
        ->where('entriesCount', 0)
        ->where('stats', [])
        ->where('heatmap', []));
});
