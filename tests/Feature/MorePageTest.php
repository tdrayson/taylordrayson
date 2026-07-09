<?php

use App\Models\Flight;
use App\Models\Page;
use App\Models\User;

use function Pest\Laravel\actingAs;
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

it('indexes published pages on /pages', function () {
    Page::factory()->create(['title' => 'Sleep score', 'slug' => 'sleep-score', 'published' => true]);
    Page::factory()->create(['title' => 'Secret draft', 'slug' => 'secret-draft', 'published' => false]);

    get('/pages')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Pages')
            ->has('pages', 1)
            ->where('pages.0.title', 'Sleep score')
            ->where('pages.0.href', '/sleep-score'));
});

it('shows unpublished pages on /pages to authenticated users', function () {
    Page::factory()->create(['title' => 'Secret draft', 'slug' => 'secret-draft', 'published' => false]);

    actingAs(User::factory()->create())
        ->get('/pages')
        ->assertInertia(fn ($page) => $page->has('pages', 1));
});
