<?php

use App\Models\Checkin;
use App\Models\Event;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

/** A Google reverse-geocode result carrying a street and a postcode. */
function fakeReverseGeocode(): void
{
    Saloon::fake(['maps.googleapis.com*' => MockResponse::make(['results' => [[
        'formatted_address' => 'Alexandra Palace Way, London N22 7AY, UK',
        'address_components' => [
            ['long_name' => 'Alexandra Palace Way', 'types' => ['route']],
            ['long_name' => 'N22 7AY', 'types' => ['postal_code']],
            ['long_name' => 'London', 'types' => ['postal_town']],
            ['long_name' => 'United Kingdom', 'types' => ['country']],
        ],
        'geometry' => ['location' => ['lat' => 51.594, 'lng' => -0.13]],
    ]]])]);
}

beforeEach(fn () => config(['services.google.maps_key' => 'test-key']));

it('fills the street and postcode an entry could not store before', function () {
    fakeReverseGeocode();

    $event = Event::factory()->create(['latitude' => 51.594, 'longitude' => -0.13, 'address' => null, 'postcode' => null]);

    $this->artisan('entries:backfill-addresses --apply')->assertSuccessful();

    expect($event->fresh())
        ->address->toBe('Alexandra Palace Way')
        ->postcode->toBe('N22 7AY');
});

it('writes nothing without --apply', function () {
    fakeReverseGeocode();

    $event = Event::factory()->create(['latitude' => 51.594, 'longitude' => -0.13, 'address' => null, 'postcode' => null]);

    $this->artisan('entries:backfill-addresses')->assertSuccessful();

    expect($event->fresh()->address)->toBeNull();
});

/**
 * A value already on the row was entered or synced deliberately, so it outranks
 * anything guessed from a coordinate.
 */
it('does not overwrite an address that is already there', function () {
    fakeReverseGeocode();

    $event = Event::factory()->create([
        'latitude' => 51.594,
        'longitude' => -0.13,
        'address' => 'The side entrance',
        'postcode' => null,
    ]);

    $this->artisan('entries:backfill-addresses --apply')->assertSuccessful();

    expect($event->fresh())
        ->address->toBe('The side entrance')
        ->postcode->toBe('N22 7AY');
});

it('leaves alone a row whose coordinates resolve to nothing useful', function () {
    Saloon::fake(['maps.googleapis.com*' => MockResponse::make(['results' => []])]);

    $checkin = Checkin::factory()->create(['latitude' => 0, 'longitude' => 0, 'postcode' => null]);

    $this->artisan('entries:backfill-addresses --apply')->assertSuccessful();

    expect($checkin->fresh()->postcode)->toBeNull();
});

it('skips entries with no coordinates to work from', function () {
    Event::factory()->create(['latitude' => null, 'longitude' => null, 'address' => null, 'postcode' => null]);

    // No mock at all: reaching Google would be a stray request and fail loudly.
    $this->artisan('entries:backfill-addresses --apply')->assertSuccessful();
});
