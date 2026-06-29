<?php

use App\Models\User;

it('renders the dashboard with grouped collection counts', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/cp')->assertSuccessful()->assertInertia(fn ($page) => $page
        ->component('Cp/Dashboard')
        ->has('collections', 2)
        ->where('collections.0.group', 'Timeline')
        ->where('collections.1.group', 'Reference')
        ->has('collections.0.items.0.count'));
});
