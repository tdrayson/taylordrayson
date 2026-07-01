<?php

use App\Models\Activity;
use App\Models\Checkin;
use Statamic\Facades\Entry;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

it('returns matching entries with a navigable url', function () {
    Activity::factory()->create(['name' => 'Parkrun at Lloyd Park', 'type' => 'run', 'occurred_at' => '2026-03-15 08:00:00']);
    Activity::factory()->create(['name' => 'Evening Walk', 'type' => 'walk', 'occurred_at' => '2026-03-16 18:00:00']);

    getJson('/search/suggest?q=parkrun')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 1)
        ->where('results.0.title', 'Parkrun at Lloyd Park')
        ->where('results.0.type', 'activity')
        ->where('results.0.url', fn ($url) => str_contains($url, '/2026/03/15/'))
        ->etc()
    );
});

it('searches across multiple types and orders by recency', function () {
    Checkin::factory()->create(['venue_name' => 'Coffee Lab', 'occurred_at' => '2026-01-10 09:00:00']);

    // The note is sourced from Statamic, not the Eloquent morph.
    $bard = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Thinking about coffee roasting']]]];
    Entry::make()->collection('notes')->slug('coffee-note')
        ->date('2026-05-01')->data(['content' => $bard])->save();

    getJson('/search/suggest?q=coffee')->assertOk()->assertJson(fn ($json) => $json
        ->has('results', 2)
        ->where('results.0.type', 'note')
        ->where('results.1.type', 'checkin')
        ->etc()
    );
});

it('ignores queries shorter than two characters', function () {
    Activity::factory()->create(['name' => 'Run', 'type' => 'run', 'occurred_at' => now()]);

    getJson('/search/suggest?q=r')->assertOk()->assertExactJson(['results' => [], 'destinations' => []]);
});

it('suggests taxonomy destination pages drawn live from the registry', function () {
    Activity::factory()->create(['name' => 'Morning miles', 'type' => 'run', 'occurred_at' => '2026-03-15 08:00:00']);
    Activity::factory()->create(['name' => 'Evening stroll', 'type' => 'walk', 'occurred_at' => '2026-03-16 18:00:00']);

    $destinations = getJson('/search/suggest?q=run')->assertOk()->json('destinations');

    expect(collect($destinations)->firstWhere('url', '/activities/run'))
        ->toMatchArray(['label' => 'Run', 'section' => 'Activities', 'type' => 'activity']);

    expect(collect($destinations)->pluck('url'))->not->toContain('/activities/walk');
});
