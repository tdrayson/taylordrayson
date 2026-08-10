<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
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
