<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a new admin user from options', function () {
    $this->artisan('cp:admin', [
        '--email' => 'taylor@example.com',
        '--name' => 'Taylor Drayson',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $user = User::where('email', 'taylor@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Taylor Drayson');
    expect(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('updates the password of an existing admin user', function () {
    User::factory()->create(['email' => 'taylor@example.com']);

    $this->artisan('cp:admin', [
        '--email' => 'taylor@example.com',
        '--name' => 'Taylor Drayson',
        '--password' => 'new-password',
    ])->assertSuccessful();

    expect(User::where('email', 'taylor@example.com')->count())->toBe(1);
    expect(Hash::check('new-password', User::first()->password))->toBeTrue();
});
