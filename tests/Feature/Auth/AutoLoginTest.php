<?php

use App\Models\User;

test('visiting the login form signs you in while auto login is on', function () {
    config(['app.auto_login' => true]);
    $user = User::factory()->create();

    $this->get('/login')->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('auto login prefers the configured account over the oldest row', function () {
    config(['app.auto_login' => true, 'app.cp.email' => 'cp@example.com']);
    User::factory()->create(['email' => 'someone-else@example.com']);
    $cp = User::factory()->create(['email' => 'cp@example.com']);

    $this->get('/login')->assertRedirect('/');

    $this->assertAuthenticatedAs($cp);
});

test('auto login shows the form with a hint when no account has been seeded', function () {
    config(['app.auto_login' => true]);

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('status', 'No account exists yet. Run `php artisan db:seed --class=UserSeeder`.'));

    $this->assertGuest();
});

test('the form is shown as normal while auto login is off', function () {
    config(['app.auto_login' => false]);
    User::factory()->create();

    $this->get('/login')->assertOk();

    $this->assertGuest();
});

test('auto login is ignored in production even with the flag on', function () {
    // Stands in for a .env carried onto a server: only the environment check
    // keeps the password form in front of it.
    config(['app.auto_login' => true]);
    app()->detectEnvironment(fn () => 'production');
    User::factory()->create();

    $this->get('/login')->assertOk();

    $this->assertGuest();
});
