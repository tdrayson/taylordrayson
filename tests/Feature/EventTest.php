<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('stores the restructured event fields', function () {
    $event = Event::create([
        'occurred_at' => '2025-06-05 00:00:00',
        'ends_at' => '2025-06-07 00:00:00',
        'all_day' => true,
        'name' => 'WordCamp Europe 2025',
        'organiser' => null,
        'venue_name' => 'Congress Center Basel',
        'city' => 'Basel',
        'country' => 'Switzerland',
        'latitude' => '47.562481',
        'longitude' => '7.599253',
        'url' => 'https://europe.wordcamp.org/2025/',
        'description' => 'Went with dad.',
        'timezone' => 'Europe/Zurich',
        'meta' => ['seat' => 'Stalls F9', 'place_id' => 'abc123'],
    ])->refresh();

    expect($event->all_day)->toBeTrue()
        ->and($event->ends_at->format('Y-m-d'))->toBe('2025-06-07')
        ->and($event->meta['seat'])->toBe('Stalls F9')
        ->and($event->description)->toBe('Went with dad.');
});

it('tags events across the expanded category set', function () {
    foreach (['musical', 'magic', 'sport', 'convention', 'conference'] as $category) {
        $event = Event::factory()->create();
        $event->syncTagNames([Str::headline($category)]);
        expect($event->fresh()->tagNames())->toContain(Str::headline($category))
            ->and($event->timezone)->toBe('Europe/London');
    }
});
