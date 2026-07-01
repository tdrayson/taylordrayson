<?php

use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Flight;

it('resolves a flight timezone from departure_timezone', function () {
    expect((new Flight(['departure_timezone' => 'America/New_York']))->timezone())->toBe('America/New_York');
});

it('resolves an activity timezone from its column', function () {
    expect((new Activity(['timezone' => 'Europe/Paris']))->timezone())->toBe('Europe/Paris');
})->skip(fn () => ! in_array('timezone', (new Activity)->getFillable()), 'activities.timezone added in Task 2');

it('returns null for types without a timezone source', function () {
    expect((new Checkin)->timezone())->toBeNull();
});
