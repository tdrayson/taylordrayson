<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
 * One user, who never registers, so registration is deliberately absent: left
 * in, anyone could sign up and immediately see every draft and unpublished
 * entry through the Auth::check() gates across the app.
 *
 * Password reset is absent too, since mail is not configured and a reset link
 * that silently fails is worse than none. Change the password by re-running
 * the user seeder.
 */

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
