<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single sign-in surface. There is no dashboard to land on: signing in
 * turns on editing across the site you were already reading, so it returns you
 * wherever you came from.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->shouldAutoLogin()) {
            return $this->autoLogin($request);
        }

        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Whether a password is a formality here. The environment is checked
     * alongside the flag so a .env copied onto a server cannot switch this on.
     */
    private function shouldAutoLogin(): bool
    {
        return config('app.auto_login') && app()->environment('local', 'testing');
    }

    /**
     * Sign in the single account without the form, so a fresh local workspace
     * opens with the editing gates already on.
     */
    private function autoLogin(Request $request): Response|RedirectResponse
    {
        // A pulled production database can hold more than one row, so the
        // configured account wins over whichever happens to be first.
        $user = User::query()
            ->where('email', config('app.cp.email'))
            ->first() ?? User::query()->oldest('id')->first();

        if ($user === null) {
            return Inertia::render('Auth/Login', [
                'status' => 'No account exists yet. Run `php artisan db:seed --class=UserSeeder`.',
            ]);
        }

        Auth::guard('web')->login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
