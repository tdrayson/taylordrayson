<?php

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
