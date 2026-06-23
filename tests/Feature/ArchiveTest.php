<?php

use App\Models\Activity;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;

use function Pest\Laravel\get;

it('renders a type index with the filtered feed and count', function () {
    Activity::factory()->create(['name' => 'Morning Run', 'type' => 'run', 'occurred_at' => now()->subDay()]);
    Activity::factory()->create(['name' => 'Evening Walk', 'type' => 'walk', 'occurred_at' => now()->subDays(2)]);

    get('/activities')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('type', 'activity')
        ->where('subtitle', '2 activities')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Morning Run'))
    );
});

it('exposes taxonomy chips on the index', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => now()->subDay()]);

    get('/activities')->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->pluck('href')->contains('/activities/walk'))
    );
});

it('filters a taxonomy sub-route and sets the parent link', function () {
    Activity::factory()->create(['name' => 'Morning Run', 'type' => 'run', 'occurred_at' => now()->subDay()]);
    Activity::factory()->create(['name' => 'Evening Walk', 'type' => 'walk', 'occurred_at' => now()->subDays(2)]);

    get('/activities/walk')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('title', 'Walk activities')
        ->where('crumb', 'Walk')
        ->where('parent.href', '/activities')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Evening Walk') && ! archiveTitlesContains($groups, 'Morning Run'))
    );
});

it('filters checkins by category slug', function () {
    Checkin::factory()->create(['venue_name' => 'Blue Bottle', 'category' => 'Coffee Shop', 'occurred_at' => now()->subDay()]);
    Checkin::factory()->create(['venue_name' => 'City Gym', 'category' => 'Gym', 'occurred_at' => now()->subDays(2)]);

    get('/places/coffee-shop')->assertOk()->assertInertia(fn ($page) => $page
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Blue Bottle') && ! archiveTitlesContains($groups, 'City Gym'))
    );
});

it('filters fuel by vehicle at its own base route', function () {
    Fuel::factory()->create(['vehicle_id' => 'hn14wxp', 'station' => 'Shell M60', 'occurred_at' => now()]);

    get('/vehicles/hn14wxp')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('type', 'fuel')
        ->where('title', 'Fuel for Golf')
    );
});

it('uses dashed slugs for multi-word taxonomy values', function () {
    Activity::factory()->create(['name' => 'Push Day', 'type' => 'weight_training', 'occurred_at' => now()]);

    get('/activities')->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->pluck('href')->contains('/activities/weight-training'))
    );

    get('/activities/weight-training')->assertOk()->assertInertia(fn ($page) => $page
        ->where('title', 'Weight Training activities')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Push Day'))
    );
});

it('404s for an unknown taxonomy value', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);

    get('/activities/does-not-exist')->assertNotFound();
});

it('paginates the archive', function () {
    Activity::factory()->count(26)->create(['type' => 'run', 'occurred_at' => fn () => fake()->dateTimeBetween('-3 months')]);

    get('/activities')->assertInertia(fn ($page) => $page->where('currentPage', 1)->where('lastPage', 2));
    get('/activities?page=2')->assertInertia(fn ($page) => $page->where('currentPage', 2));
});

it('renders every flight on the overview map on the first page only', function () {
    Airport::create(['iata_code' => 'LHR', 'name' => 'Heathrow', 'city' => 'London', 'country' => 'GB', 'latitude' => 51.47, 'longitude' => -0.4543]);
    Airport::create(['iata_code' => 'JFK', 'name' => 'JFK', 'city' => 'New York', 'country' => 'US', 'latitude' => 40.6413, 'longitude' => -73.7781]);

    Flight::factory()->count(26)->create([
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'occurred_at' => fn () => fake()->dateTimeBetween('-3 months'),
    ]);

    get('/flights')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('type', 'flight')
        ->has('map', 26)
        ->where('map.0.origin.iata', 'LHR')
        ->where('map.0.destination.iata', 'JFK')
        ->where('map.0.origin.lat', 51.47)
    );

    get('/flights?page=2')->assertInertia(fn ($page) => $page->where('map', []));
});

it('uses the airline name slug for the flight taxonomy and filters by it', function () {
    Airline::create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
    Airport::create(['iata_code' => 'LHR', 'name' => 'Heathrow', 'latitude' => 51.47, 'longitude' => -0.4543]);
    Airport::create(['iata_code' => 'JFK', 'name' => 'JFK', 'latitude' => 40.6413, 'longitude' => -73.7781]);
    Airport::create(['iata_code' => 'CDG', 'name' => 'Charles de Gaulle', 'latitude' => 49.0097, 'longitude' => 2.5479]);

    Flight::factory()->create(['airline_icao' => 'BAW', 'origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'occurred_at' => now()->subDay()]);
    Flight::factory()->create(['airline_icao' => 'AFR', 'origin_iata' => 'LHR', 'destination_iata' => 'CDG', 'occurred_at' => now()->subDays(2)]);

    get('/flights')->assertInertia(fn ($page) => $page
        ->where('subtitle', '2 flights')
        ->has('map', 2)
        ->where('chips', fn ($chips) => collect($chips)->pluck('href')->contains('/flights/british-airways'))
    );

    get('/flights/british-airways')->assertOk()->assertInertia(fn ($page) => $page
        ->where('title', 'Flights with British Airways')
        ->where('crumb', 'British Airways')
        ->where('parent.href', '/flights')
        ->where('subtitle', '1 flight')
        ->has('map', 1)
        ->where('map.0.destination.iata', 'JFK')
    );

    get('/flights/baw')->assertNotFound();
});

it('exposes route geometry on feed cards (activity polyline, flight coords)', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now(), 'meta' => ['polyline' => 'abc123']]);

    get('/activities/run')->assertInertia(fn ($page) => $page
        ->where('groups', fn ($groups) => collect($groups)
            ->flatMap(fn ($group) => $group['items'])
            ->contains(fn ($item) => ($item['polyline'] ?? null) === 'abc123'))
    );

    Airport::create(['iata_code' => 'LHR', 'name' => 'Heathrow', 'latitude' => 51.47, 'longitude' => -0.4543]);
    Airport::create(['iata_code' => 'JFK', 'name' => 'JFK', 'latitude' => 40.6413, 'longitude' => -73.7781]);
    Flight::factory()->create(['origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'occurred_at' => now()]);

    get('/flights')->assertInertia(fn ($page) => $page
        ->where('groups', fn ($groups) => collect($groups)
            ->flatMap(fn ($group) => $group['items'])
            ->contains(fn ($item) => ($item['route']['origin']['lat'] ?? null) === 51.47))
    );
});

it('omits the overview map for non-flight archives', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);

    get('/activities')->assertInertia(fn ($page) => $page->where('map', []));
});

function archiveTitlesContains($groups, string $title): bool
{
    return collect($groups)->flatMap(fn ($group) => collect($group['items'])->pluck('title'))->contains($title);
}
