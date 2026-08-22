<?php

use App\Models\Flight;
use App\Models\Page;

use function Pest\Laravel\get;

it('lists every tracked type with its live count on /more', function () {
    Flight::factory()->count(2)->create();

    get('/more')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('More')
            ->has('tracked', 13)
            ->where('tracked', fn ($tracked) => collect($tracked)->contains(
                fn ($type) => $type['label'] === 'Flights' && $type['href'] === '/flights' && $type['count'] === 2,
            )));
});

it('404s the removed /pages index', function () {
    Page::factory()->create(['slug' => 'sleep-score', 'published' => true]);

    // /pages now falls through to the slug catch-all, so it only resolves if
    // someone authors a page actually called "pages".
    get('/pages')->assertNotFound();
});
