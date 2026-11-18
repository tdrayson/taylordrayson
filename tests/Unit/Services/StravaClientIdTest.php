<?php

use App\Services\Strava\Client;

it('prefers id_str so an id past PHP_INT_MAX keeps its digits', function () {
    expect(Client::id(['id' => 1.8446744073709552E+19, 'id_str' => '18446744073709551615']))
        ->toBe('18446744073709551615');
});

it('falls back to the integer id when id_str is absent', function () {
    expect(Client::id(['id' => 12345678901]))->toBe('12345678901');
});
