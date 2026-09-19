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

it('sends the entry count as a number even when the cache hands back a numeric string', function () {
    Cache::put('error.entry_count', '10354', now()->addHour());

    get('/this-page-does-not-exist')
        ->assertInertia(fn (Assert $page) => $page->whereType('entries', 'integer'));
});

it('takes the streak from the shared prop rather than a days prop of its own', function () {
    get('/this-page-does-not-exist')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('days')
            ->whereType('streakDays', 'integer')
        );
});
