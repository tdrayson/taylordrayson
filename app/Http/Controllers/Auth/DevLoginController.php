<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign in the single account without the form, so a freshly provisioned
 * workspace opens with the editing gates already on. Local only, and off
 * unless DEV_AUTO_LOGIN says otherwise.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // The environment is checked again rather than trusted from route
        // registration: a route cache built locally and then deployed would
        // carry this route into production with it.
        abort_unless(
            app()->environment('local', 'testing') && config('app.dev_auto_login'),
            404,
        );

        if (Auth::check()) {
            return redirect('/');
        }

        // A pulled production database can hold more than one row, so the
        // configured account wins over whichever happens to be first.
        $user = User::query()
            ->where('email', config('app.cp.email'))
            ->first() ?? User::query()->oldest('id')->first();

        if ($user === null) {
            return redirect()->route('login')->with(
                'status',
                'No account exists yet. Run `php artisan db:seed --class=UserSeeder`.',
            );
        }

        Auth::guard('web')->login($user, remember: true);

        $request->session()->regenerate();

        return redirect('/');
    }
}
