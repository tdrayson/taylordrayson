<?php

use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Note;

use function Pest\Laravel\getJson;

it('returns matching entries with a navigable url', function () {
    Activity::factory()->create(['name' => 'Parkrun at Lloyd Park', 'type' => 'run', 'occurred_at' => '2026-03-15 08:00:00']);
    Activity::factory()->create(['name' => 'Evening Walk', 'type' => 'walk', 'occurred_at' => '2026-03-16 18:00:00']);

    getJson('/search/suggest?q=parkrun')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 1)
        ->where('results.0.title', 'Parkrun at Lloyd Park')
        ->where('results.0.type', 'activity')
        ->where('results.0.url', fn ($url) => str_contains($url, '/2026/03/15/'))
    );
});

it('searches across multiple types and orders by recency', function () {
    Checkin::factory()->create(['venue_name' => 'Coffee Lab', 'occurred_at' => '2026-01-10 09:00:00']);
    Note::factory()->create(['content' => 'Thinking about coffee roasting', 'occurred_at' => '2026-05-01 09:00:00']);

    getJson('/search/suggest?q=coffee')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 2)
        ->where('results.0.type', 'note')
        ->where('results.1.type', 'checkin')
    );
});

it('ignores queries shorter than two characters', function () {
    Activity::factory()->create(['name' => 'Run', 'type' => 'run', 'occurred_at' => now()]);

    getJson('/search/suggest?q=r')->assertOk()->assertExactJson(['results' => []]);
});

it('narrows free text to a single type', function () {
    Activity::factory()->create(['name' => 'Coffee run', 'type' => 'run', 'occurred_at' => '2026-02-01 08:00:00']);
    Checkin::factory()->create(['venue_name' => 'Coffee Lab', 'occurred_at' => '2026-02-02 09:00:00']);

    getJson('/search/suggest?q=coffee&type=activity')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 1)
        ->where('results.0.type', 'activity')
    );
});

it('filters by a date range with no free text', function () {
    Activity::factory()->create(['name' => 'In range', 'type' => 'run', 'occurred_at' => '2026-03-15 08:00:00']);
    Activity::factory()->create(['name' => 'Out of range', 'type' => 'run', 'occurred_at' => '2026-04-15 08:00:00']);

    getJson('/search/suggest?from=2026-03-01T00:00:00&to=2026-03-31T23:59:59')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 1)
        ->where('results.0.title', 'In range')
    );
});

it('combines type and date range', function () {
    Activity::factory()->create(['name' => 'Match', 'type' => 'run', 'occurred_at' => '2026-03-10 08:00:00']);
    Checkin::factory()->create(['venue_name' => 'Same day place', 'occurred_at' => '2026-03-10 09:00:00']);

    getJson('/search/suggest?type=activity&from=2026-03-01T00:00:00&to=2026-03-31T23:59:59')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 1)
        ->where('results.0.type', 'activity')
    );
});
