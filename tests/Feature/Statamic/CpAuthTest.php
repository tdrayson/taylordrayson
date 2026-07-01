<?php

/**
 * Guards the flat-file CP auth setup. Regression: when the web guard used the
 * Eloquent user provider, a stale web session made Statamic's RedirectIfAuthorized
 * call ->can() on a null User::current() (the file repository had no such user),
 * 500ing /cp/auth/login. The single flat-file provider keeps the guard and
 * Statamic's current user in sync.
 */
it('resolves users through the Statamic flat-file provider, not Eloquent', function () {
    expect(config('auth.providers.users.driver'))->toBe('statamic');
});

it('serves the CP login form without error', function () {
    $this->get('/cp/auth/login')->assertOk();
});

it('redirects the CP root to login when unauthenticated', function () {
    $this->get('/cp')->assertRedirect('/cp/auth/login');
});
