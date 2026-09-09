<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DevLoginController;
use Illuminate\Support\Facades\Route;

/*
 * One user who never registers, so registration is deliberately absent: it would
 * let anyone sign up and see every draft through the Auth::check() gates. Reset
 * is absent too, mail being unconfigured; re-run the user seeder instead.
 */

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
 * Passwordless sign-in for a local workspace, outside the guest group so an
 * already-signed-in visit is a plain redirect home rather than a bounce. Two
 * gates, neither sufficient alone: the route is not registered outside local,
 * and the controller 404s unless the environment still holds and
 * DEV_AUTO_LOGIN is on.
 */
if (app()->environment('local', 'testing')) {
    Route::get('dev-login', DevLoginController::class)->name('dev-login');
}
