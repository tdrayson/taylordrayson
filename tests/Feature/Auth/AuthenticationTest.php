<?php

use App\Models\User;
use Illuminate\Http\Request;

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk();
});

test('signing in returns you to where you came from, since there is no dashboard', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('registration is not reachable, so nobody can grant themselves the editing gates', function () {
    // No named route at all is the real assertion; the status codes are an
    // accident of /{slug} catching 'register' (GET 404s, POST is a 405).
    expect(app('router')->has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::count())->toBe(0);
});

test('the login route wins over the page catch-all', function () {
    // /{slug} matches any lowercase word, 'login' included. Registered later it
    // would resolve to PageController and 404 instead of showing the form.
    expect(app('router')->getRoutes()->match(
        Request::create('/login', 'GET')
    )->getName())->toBe('login');
});
