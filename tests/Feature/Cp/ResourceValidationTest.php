<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('requires occurred_at on a timeline resource', function () {
    $this->from('/cp/flights/create')
        ->post('/cp/flights', ['flight_number' => 'BA1'])
        ->assertSessionHasErrors('occurred_at');
});

it('rejects a non-numeric number field', function () {
    $this->from('/cp/flights/create')
        ->post('/cp/flights', ['occurred_at' => '2026-07-01T09:30', 'duration' => 'not-a-number'])
        ->assertSessionHasErrors('duration');
});
