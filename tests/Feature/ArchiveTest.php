<?php

use App\Models\Activity;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Article;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\User;

use function Pest\Laravel\actingAs;
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

it('keeps the chips on a taxonomy page and flags the active one', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()->subDay()]);
    Activity::factory()->create(['type' => 'walk', 'occurred_at' => now()->subDays(2)]);

    get('/activities/walk')->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->firstWhere('href', '/activities/walk')['active'] === true
            && collect($chips)->firstWhere('href', '/activities/run')['active'] === false)
    );
});

it('filters checkins by category slug', function () {
    Checkin::factory()->create(['venue_name' => 'Blue Bottle', 'category' => 'Coffee Shop', 'occurred_at' => now()->subDay()]);
    Checkin::factory()->create(['venue_name' => 'City Gym', 'category' => 'Gym', 'occurred_at' => now()->subDays(2)]);

    get('/places/coffee-shop')->assertOk()->assertInertia(fn ($page) => $page
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Blue Bottle') && ! archiveTitlesContains($groups, 'City Gym'))
    );
});

it('filters This Week With by season, with season-ordered chips', function () {
    Podcast::factory()->create(['season_number' => 3, 'episode_number' => 1, 'occurred_at' => now()->subDay()]);
    Podcast::factory()->create(['season_number' => 3, 'episode_number' => 2, 'occurred_at' => now()->subDays(2)]);
    Podcast::factory()->create(['season_number' => 5, 'episode_number' => 1, 'occurred_at' => now()->subDays(3)]);

    get('/this-week-with')->assertOk()->assertInertia(fn ($page) => $page
        ->where('type', 'podcast')
        // The leading "all" chip reads "All Seasons", not "All This Week With".
        ->where('chips', fn ($chips) => collect($chips)->firstWhere('all', true)['label'] === 'All Seasons'
            && collect($chips)->firstWhere('href', '/this-week-with/3')['label'] === 'Season 3'
            // Seasons run in numeric order, not most-used-first (ignore the leading "All" chip).
            && collect($chips)->reject(fn ($chip) => $chip['all'] ?? false)->pluck('label')->all() === ['Season 3', 'Season 5'])
    );

    get('/this-week-with/3')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('title', 'Season 3')
        ->where('crumb', 'Season 3')
        ->where('parent.href', '/this-week-with')
        ->where('subtitle', '2 episodes')
    );

    get('/this-week-with/99')->assertNotFound();
    // Non-canonical numeric forms must not slip past the strict guard.
    get('/this-week-with/03')->assertNotFound();
});

it('filters fuel by vehicle at its own base route', function () {
    Fuel::factory()->create(['vehicle_id' => 'hn14wxp', 'occurred_at' => now()]);

    get('/vehicles/hn14wxp')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('type', 'fuel')
        ->where('title', 'Fuel for Toyota Aygo')
    );
});

it('uses dashed slugs for multi-word taxonomy values', function () {
    Activity::factory()->create(['name' => 'Push Day', 'type' => 'weight-training', 'occurred_at' => now()]);

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

it('omits the overview map for archives without one', function () {
    Activity::factory()->create(['type' => 'run', 'occurred_at' => now()]);

    get('/activities')->assertInertia(fn ($page) => $page->where('map', []));
});

it('renders unique fuel stations on the overview map on the first page only', function () {
    Fuel::factory()->create([
        'station_name' => 'BP Chippenham',
        'latitude' => 51.46,
        'longitude' => -2.12,
        'occurred_at' => now()->subDay(),
    ]);
    // Same pump again — should collapse to one pin.
    Fuel::factory()->create([
        'station_name' => 'BP Chippenham',
        'latitude' => 51.46,
        'longitude' => -2.12,
        'occurred_at' => now()->subDays(2),
    ]);
    Fuel::factory()->create([
        'station_name' => 'Shell Bath',
        'latitude' => 51.38,
        'longitude' => -2.36,
        'occurred_at' => now()->subDays(3),
    ]);
    Fuel::factory()->create([
        'station_name' => 'No coords',
        'latitude' => null,
        'longitude' => null,
        'occurred_at' => now()->subDays(4),
    ]);
    // Pad past the first page so page 2 exists and should omit the map.
    Fuel::factory()->count(23)->create([
        'latitude' => null,
        'longitude' => null,
        'occurred_at' => fn () => fake()->dateTimeBetween('-3 months', '-5 days'),
    ]);

    get('/fuel')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('type', 'fuel')
        ->has('map', 2)
        ->where('map.0.label', 'BP Chippenham')
        ->where('map.0.lat', 51.46)
        ->where('map', fn ($map) => collect($map)->pluck('label')->contains('Shell Bath'))
    );

    get('/fuel?page=2')->assertInertia(fn ($page) => $page->where('map', []));
});

it('filters the fuel overview map by vehicle taxonomy', function () {
    Fuel::factory()->create([
        'vehicle_id' => 'hn14wxp',
        'station_name' => 'Aygo fill',
        'latitude' => 51.46,
        'longitude' => -2.12,
        'occurred_at' => now()->subDay(),
    ]);
    Fuel::factory()->create([
        'vehicle_id' => 'other-car',
        'station_name' => 'Other fill',
        'latitude' => 51.50,
        'longitude' => -2.20,
        'occurred_at' => now()->subDays(2),
    ]);

    get('/vehicles/hn14wxp')->assertOk()->assertInertia(fn ($page) => $page
        ->where('type', 'fuel')
        ->has('map', 1)
        ->where('map.0.label', 'Aygo fill')
    );
});

it('filters the project archive by relational tag slug', function () {
    $vue = Project::factory()->create(['title' => 'Vue Component Library', 'occurred_at' => now()->subDay()]);
    $other = Project::factory()->create(['title' => 'API Gateway', 'occurred_at' => now()->subDays(2)]);
    $vue->syncTagNames(['Vue', 'Tailwind']);
    $other->syncTagNames(['Laravel']);

    get('/projects')->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->pluck('href')->contains('/projects/vue'))
    );

    get('/projects/vue')->assertOk()->assertInertia(fn ($page) => $page
        ->where('title', 'Projects tagged Vue')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Vue Component Library') && ! archiveTitlesContains($groups, 'API Gateway'))
    );
});

it('filters the article archive by relational tag slug', function () {
    $laravel = Article::factory()->create(['published' => true, 'title' => 'Laravel Tips', 'occurred_at' => now()->subDay()]);
    $other = Article::factory()->create(['published' => true, 'title' => 'A Day Out', 'occurred_at' => now()->subDays(2)]);
    $laravel->syncTagNames(['Laravel']);
    $other->syncTagNames(['Travel']);

    get('/articles/laravel')->assertOk()->assertInertia(fn ($page) => $page
        ->where('title', 'Articles tagged Laravel')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Laravel Tips') && ! archiveTitlesContains($groups, 'A Day Out'))
    );
});

it('hides tags used only on unpublished articles from guest archive chips', function () {
    $public = Article::factory()->create(['published' => true, 'title' => 'Public Post', 'occurred_at' => now()->subDay()]);
    $secret = Article::factory()->create(['published' => false, 'title' => 'Secret Launch Post', 'occurred_at' => now()->subDays(2)]);
    $public->syncTagNames(['Public Topic']);
    $secret->syncTagNames(['Secret Launch']);

    get('/articles')->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->pluck('label')->contains('Public Topic')
            && ! collect($chips)->pluck('label')->contains('Secret Launch'))
    );

    get('/articles/secret-launch')->assertNotFound();
});

it('shows draft-only tags to the authenticated user', function () {
    $public = Article::factory()->create(['published' => true, 'title' => 'Public Post', 'occurred_at' => now()->subDay()]);
    $secret = Article::factory()->create(['published' => false, 'title' => 'Secret Launch Post', 'occurred_at' => now()->subDays(2)]);
    $public->syncTagNames(['Public Topic']);
    $secret->syncTagNames(['Secret Launch']);

    actingAs(User::factory()->create());

    get('/articles')->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->pluck('label')->contains('Public Topic')
            && collect($chips)->pluck('label')->contains('Secret Launch'))
    );
});

it('registers a tag taxonomy on the note archive too', function () {
    $coffee = Note::factory()->create(['content' => 'Espresso notes', 'occurred_at' => now()->subDay()]);
    $other = Note::factory()->create(['content' => 'Random thought', 'occurred_at' => now()->subDays(2)]);
    $coffee->syncTagNames(['Coffee']);
    $other->syncTagNames(['Life']);

    get('/notes/coffee')->assertOk()->assertInertia(fn ($page) => $page
        ->where('title', 'Notes tagged Coffee')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Espresso notes') && ! archiveTitlesContains($groups, 'Random thought'))
    );
});

function archiveTitlesContains($groups, string $title): bool
{
    return collect($groups)->flatMap(fn ($group) => collect($group['items'])->pluck('title'))->contains($title);
}
