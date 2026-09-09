<?php

use App\Models\User;

test('dev login signs in the account and lands on the homepage', function () {
    config(['app.dev_auto_login' => true]);
    $user = User::factory()->create();

    $this->get('/dev-login')->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('dev login is a plain redirect home when already signed in', function () {
    config(['app.dev_auto_login' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dev-login')->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('dev login 404s while the flag is off', function () {
    config(['app.dev_auto_login' => false]);
    User::factory()->create();

    $this->get('/dev-login')->assertNotFound();

    $this->assertGuest();
});

test('dev login sends you to the login form when no account has been seeded', function () {
    config(['app.dev_auto_login' => true]);

    $this->get('/dev-login')
        ->assertRedirect('/login')
        ->assertSessionHas('status');

    $this->assertGuest();
});

test('dev login prefers the configured account over the oldest row', function () {
    config(['app.dev_auto_login' => true, 'app.cp.email' => 'cp@example.com']);
    User::factory()->create(['email' => 'someone-else@example.com']);
    $cp = User::factory()->create(['email' => 'cp@example.com']);

    $this->get('/dev-login')->assertRedirect('/');

    $this->assertAuthenticatedAs($cp);
});

test('dev login 404s in production even with the flag on and the route registered', function () {
    // Stands in for a route cache built locally and deployed: the route exists,
    // so only the controller's own environment check can turn it away.
    config(['app.dev_auto_login' => true]);
    app()->detectEnvironment(fn () => 'production');
    User::factory()->create();

    $this->get('/dev-login')->assertNotFound();

    $this->assertGuest();
});
