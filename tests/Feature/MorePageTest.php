<?php

use App\Models\Flight;
use App\Models\Note;
use App\Models\Page;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;

use function Pest\Laravel\get;

it('lists every tracked type with its live count on /more, in registry order', function () {
    Flight::factory()->count(2)->create();

    $keys = array_keys(TypeRegistry::all());

    get('/more')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('More')
            ->has('tracked', count($keys))
            ->where('tracked', fn ($tracked) => collect($tracked)->pluck('type')->all() === $keys)
            ->where('tracked', fn ($tracked) => collect($tracked)
                ->contains(fn ($type) => $type['label'] === 'Flights' && $type['href'] === '/flights' && $type['count'] === 2)));
});

it('states the total entry count, matching the per-type counts', function () {
    Flight::factory()->count(2)->create();
    Note::factory()->count(3)->create();

    get('/more')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('total', 5)
            ->where('total', TimelineEntry::query()->count())
            ->where('total', fn ($total) => $total === collect($page->toArray()['props']['tracked'])->sum('count')));
});

it('404s the removed /pages index', function () {
    Page::factory()->create(['slug' => 'sleep-score', 'status' => 'published']);

    // /pages now falls through to the slug catch-all, so it only resolves if
    // someone authors a page actually called "pages".
    get('/pages')->assertNotFound();
});
