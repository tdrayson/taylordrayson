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
        $email = config('app.cp.email');
        $password = config('app.cp.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set CP_EMAIL and CP_PASSWORD before seeding the control panel account.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('app.cp.name'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info($user->wasRecentlyCreated
            ? "Created the control panel account for {$email}."
            : "Updated the password for {$email}.");
    }
}
