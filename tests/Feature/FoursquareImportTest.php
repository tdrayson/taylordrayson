<?php

use App\Models\Checkin;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.foursquare.access_token' => 'test-token']);
});

it('imports checkins from the foursquare api', function () {
    Http::fake([
        '*users/self/checkins*' => Http::sequence()
            ->push(['response' => ['checkins' => ['items' => [[
                'id' => 'abc',
                'createdAt' => 1700000000,
                'shout' => 'Great coffee',
                'isMayor' => true,
                'venue' => [
                    'name' => 'Coffee Bar',
                    'categories' => [['name' => 'Café']],
                    'location' => ['address' => '1 High St', 'city' => 'London', 'state' => 'England', 'country' => 'UK', 'lat' => 51.5, 'lng' => -0.1],
                ],
            ]]]]])
            ->push(['response' => ['checkins' => ['items' => []]]]),
    ]);

    $this->artisan('foursquare:import')->assertSuccessful();

    $checkin = Checkin::where('platform_id', 'abc')->first();
    expect($checkin)->not->toBeNull();
    expect($checkin->venue_name)->toBe('Coffee Bar');
    expect($checkin->category)->toBe('Café');
    expect($checkin->city)->toBe('London');
    expect($checkin->is_mayor)->toBeTrue();
});

it('skips checkins that already exist', function () {
    Checkin::factory()->create(['platform_type' => 'swarm', 'platform_id' => 'dupe']);

    Http::fake([
        '*users/self/checkins*' => Http::sequence()
            ->push(['response' => ['checkins' => ['items' => [[
                'id' => 'dupe',
                'createdAt' => 1700000000,
                'venue' => ['name' => 'Seen Before'],
            ]]]]])
            ->push(['response' => ['checkins' => ['items' => []]]]),
    ]);

    $this->artisan('foursquare:import')->assertSuccessful();

    expect(Checkin::where('platform_id', 'dupe')->count())->toBe(1);
});

it('fails when the api errors', function () {
    Http::fake(['*users/self/checkins*' => Http::response('boom', 500)]);

    $this->artisan('foursquare:import')->assertFailed();
});

it('fails when the access token is not configured', function () {
    config(['services.foursquare.access_token' => null]);

    $this->artisan('foursquare:import')->assertFailed();
});
