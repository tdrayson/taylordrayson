<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Create or update the single account that can sign in and edit.
 *
 * There is no registration route, so this seeder is the only way an account
 * comes into existence. It is safe to run against a populated database: it
 * touches nothing but the one user row, and re-running it changes the password
 * in place, which doubles as the password reset (mail is not configured, so
 * there is no emailed reset link).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.owner.email');
        $password = config('app.owner.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set OWNER_EMAIL and OWNER_PASSWORD before seeding the owner account.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('app.owner.name'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info($user->wasRecentlyCreated
            ? "Created the owner account for {$email}."
            : "Updated the password for {$email}.");
    }
}
