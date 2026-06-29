<?php

use App\Models\User;

it('shares a null auth user for guests', function () {
    $this->get('/cp/login')
        ->assertInertia(fn ($page) => $page->where('auth.user', null));
});

it('shares the auth user and cp nav when authenticated', function () {
    $this->actingAs(User::factory()->create(['name' => 'Taylor Drayson', 'email' => 'taylor@example.com']));

    $this->get('/cp')->assertInertia(fn ($page) => $page
        ->where('auth.user.name', 'Taylor Drayson')
        ->where('auth.user.email', 'taylor@example.com')
        ->has('cp.nav'));
});
