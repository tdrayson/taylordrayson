<?php

use App\Models\Checkin;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.foursquare.access_token' => 'test-token']);
});

it('imports checkins from the foursquare api', function () {
    Saloon::fake([
        MockResponse::make(['response' => ['checkins' => ['items' => [[
            'id' => 'abc',
            'createdAt' => 1700000000,
            'shout' => 'Great coffee',
            'venue' => [
                'name' => 'Coffee Bar',
                'categories' => [['name' => 'Café']],
                'location' => ['address' => '1 High St', 'city' => 'London', 'state' => 'England', 'country' => 'UK', 'lat' => 51.5, 'lng' => -0.1],
            ],
        ]]]]]),
        MockResponse::make(['response' => ['checkins' => ['items' => []]]]),
    ]);

    $this->artisan('foursquare:import')->assertSuccessful();

    $checkin = Checkin::where('source_id', 'abc')->first();
    expect($checkin)->not->toBeNull();
    expect($checkin->venue_name)->toBe('Coffee Bar');
    expect($checkin->category)->toBe('Café');
    expect($checkin->city)->toBe('London');
});

it('skips checkins that already exist', function () {
    Checkin::factory()->create(['source' => 'swarm', 'source_id' => 'dupe']);

    Saloon::fake([
        MockResponse::make(['response' => ['checkins' => ['items' => [[
            'id' => 'dupe',
            'createdAt' => 1700000000,
            'venue' => ['name' => 'Seen Before'],
        ]]]]]),
        MockResponse::make(['response' => ['checkins' => ['items' => []]]]),
    ]);

    $this->artisan('foursquare:import')->assertSuccessful();

    expect(Checkin::where('source_id', 'dupe')->count())->toBe(1);
});

it('fails when the api errors', function () {
    Saloon::fake(['users/self/checkins*' => MockResponse::make('boom', 500)]);

    $this->artisan('foursquare:import')->assertFailed();
});

it('fails when the access token is not configured', function () {
    config(['services.foursquare.access_token' => null]);

    $this->artisan('foursquare:import')->assertFailed();
});
