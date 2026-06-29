<?php

use App\Models\User;

it('redirects guests from the control panel to the login page', function () {
    $this->get('/cp')->assertRedirect(route('cp.login'));
});

it('shows the login page to guests', function () {
    $this->get('/cp/login')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Cp/Login'));
});

it('logs in with valid credentials', function () {
    User::factory()->create(['email' => 'taylor@example.com', 'password' => 'secret-password']);

    $this->post('/cp/login', ['email' => 'taylor@example.com', 'password' => 'secret-password'])
        ->assertRedirect(route('cp.dashboard'));

    $this->assertAuthenticated();
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'taylor@example.com', 'password' => 'secret-password']);

    $this->from('/cp/login')
        ->post('/cp/login', ['email' => 'taylor@example.com', 'password' => 'wrong'])
        ->assertRedirect('/cp/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/cp/logout')->assertRedirect(route('cp.login'));

    $this->assertGuest();
});

it('allows an authenticated user into the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/cp')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Cp/Dashboard', false));
});
