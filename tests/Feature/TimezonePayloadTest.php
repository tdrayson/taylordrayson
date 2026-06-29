<?php

use App\Models\Flight;

it('exposes local label, offset and offset-aware iso on the entry page', function () {
    $flight = Flight::factory()->create([
        'occurred_at' => '2026-07-01 09:30:00',
        'departure_timezone' => 'Europe/London',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
    ]);

    $this->get($flight->url())->assertInertia(fn ($page) => $page
        ->where('occurredOffset', '+01:00')
        ->where('occurredAt', '2026-07-01T09:30:00+01:00')
        ->where('occurredLabel', 'Wed 1 Jul 2026, 9:30am'));
});
