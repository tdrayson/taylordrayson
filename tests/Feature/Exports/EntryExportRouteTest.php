<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Note;
use App\Models\User;

/** A private KRK-LGW flight, carrying both a Geometry and a Span aspect, for the locked-export tests. */
function privateKrkToLgw(): Flight
{
    Airline::factory()->create(['icao_code' => 'EZY', 'iata_code' => 'U2', 'name' => 'easyJet UK']);
    Airport::factory()->create([
        'iata_code' => 'KRK', 'icao_code' => 'EPKK', 'name' => 'Kraków John Paul II International Airport',
        'city' => 'Kraków', 'latitude' => 50.077702, 'longitude' => 19.7848,
    ]);
    Airport::factory()->create([
        'iata_code' => 'LGW', 'icao_code' => 'EGKK', 'name' => 'London Gatwick Airport',
        'city' => 'London', 'latitude' => 51.148771, 'longitude' => -0.192089,
    ]);

    return Flight::factory()->create([
        'occurred_at' => '2026-06-08 22:15:00',
        'flight_number' => '8824',
        'airline_icao' => 'EZY',
        'origin_iata' => 'KRK',
        'destination_iata' => 'LGW',
        'distance' => 1409785,
        'duration' => 8820,
        'departure_timezone' => 'Europe/Warsaw',
        'arrival_timezone' => 'Europe/London',
        'cabin_class' => 'economy',
        'reason' => 'business',
        'status' => 'private',
        'password' => 'hunter2',
    ])->load('airline', 'origin', 'destination');
}

it('serves an entry as json at its own url plus an extension', function () {
    $flight = krkToLgw();

    $this->get($flight->url().'.json')
        ->assertOk()
        ->assertHeader('content-type', 'application/json; charset=UTF-8')
        ->assertJsonPath('type', 'flight');
});

it('serves yaml and sql as readable text, not a download', function () {
    $flight = krkToLgw();

    $this->get($flight->url().'.yaml')->assertOk()->assertHeader('content-type', 'text/yaml; charset=UTF-8');
    $this->get($flight->url().'.sql')->assertOk()->assertHeader('content-type', 'text/x-sql; charset=UTF-8');
});

it('no longer lists other formats in the response, since the head and footer alternates cover that', function () {
    $flight = krkToLgw();

    $response = $this->get($flight->url().'.json')->json();

    expect($response)->not->toHaveKey('formats');
});

it('404s an extension the entry does not support', function () {
    $note = Note::factory()->create(['occurred_at' => '2026-06-08 10:00:00']);

    $this->get($note->url().'.geojson')->assertNotFound();
    $this->get($note->url().'.ics')->assertNotFound();
});

it('404s an unknown slug and an unpublished entry', function () {
    $this->get('/2026/06/08/nothing-here.json')->assertNotFound();

    $draft = Flight::factory()->create(['occurred_at' => '2026-06-08 22:15:00', 'status' => 'draft']);

    $this->get($draft->url().'.json')->assertNotFound();
});

it('does not let an extension fall through to the entry page', function () {
    krkToLgw();

    $this->get('/2026/06/08/krk-lgw.json')->assertHeader('content-type', 'application/json; charset=UTF-8');
});

it('publishes only the header for a private entry the request has not unlocked', function () {
    $flight = privateKrkToLgw();

    $response = $this->get($flight->url().'.json');

    $response->assertOk()
        ->assertJsonPath('locked', true)
        ->assertJsonMissingPath('fields')
        ->assertJsonMissingPath('links')
        ->assertDontSee('8824')
        ->assertDontSee('easyJet');
});

it('404s a locked geojson and ics export rather than carrying the aspects through', function () {
    $flight = privateKrkToLgw();

    $this->get($flight->url().'.geojson')->assertNotFound();
    $this->get($flight->url().'.ics')->assertNotFound();
});

it('404s a locked sql export', function () {
    $flight = privateKrkToLgw();

    $this->get($flight->url().'.sql')->assertNotFound();
});

it('keeps a private entry export out of shared caches', function () {
    $flight = privateKrkToLgw();

    $this->get($flight->url().'.json')->assertHeader('Cache-Control', 'no-store, private');
});

it('404s a draft export for a guest and serves it to its owner', function () {
    $draft = Flight::factory()->create(['occurred_at' => '2026-06-08 22:15:00', 'status' => 'draft']);
    // A draft has no spine row, so url() points at /drafts/{dataset}/{id}; the
    // export route is reached at the dated address the owner would guess.
    $datedUrl = '/'.$draft->occurred_at->format('Y/m/d').'/'.$draft->slug();

    $this->get($datedUrl.'.json')->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get($datedUrl.'.json')
        ->assertOk();
});
