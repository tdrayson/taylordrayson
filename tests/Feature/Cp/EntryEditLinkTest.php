<?php

use App\Models\Flight;
use App\Models\User;

it('includes an edit url for an authenticated user on the flight entry page', function () {
    $flight = Flight::factory()->create();

    $this->actingAs(User::factory()->create());

    $this->get($flight->url())->assertInertia(fn ($page) => $page
        ->where('editUrl', "/cp/flights/{$flight->id}/edit"));
});

it('renders the entry page successfully for a guest', function () {
    $flight = Flight::factory()->create();

    $this->get($flight->url())->assertSuccessful();
});
