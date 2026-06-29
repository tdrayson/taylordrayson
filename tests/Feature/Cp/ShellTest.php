<?php

use App\Models\User;

it('does not expose the control panel nav to guests on the public site', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('auth.user', null)
        ->where('cp.nav', []));
});

it('shares the control panel shell data when authenticated', function () {
    $this->actingAs(User::factory()->create(['name' => 'Taylor Drayson']));

    $this->get('/cp')->assertInertia(fn ($page) => $page
        ->component('Cp/Dashboard', false)
        ->where('auth.user.name', 'Taylor Drayson')
        ->has('cp.nav'));
});
