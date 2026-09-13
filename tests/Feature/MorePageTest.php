<?php

use App\Models\Flight;
use App\Models\Page;
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

it('404s the removed /pages index', function () {
    Page::factory()->create(['slug' => 'sleep-score', 'published' => true]);

    // /pages now falls through to the slug catch-all, so it only resolves if
    // someone authors a page actually called "pages".
    get('/pages')->assertNotFound();
});
