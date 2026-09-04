<?php

use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

it('renders the custom 404 page for an unknown url', function () {
    get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->whereType('entries', 'integer')
            ->whereType('leaderboard', 'array')
        );
});

it('sends the counts as numbers even when the cache hands back numeric strings', function () {
    Cache::put('error.entry_count', '10354', now()->addHour());
    Cache::put('error.day_count', '2983', now()->addHour());

    get('/this-page-does-not-exist')
        ->assertInertia(fn (Assert $page) => $page
            ->whereType('entries', 'integer')
            ->whereType('days', 'integer')
        );
});
