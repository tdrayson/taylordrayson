<?php

use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Fuel;
use App\Support\Distance;

use function Pest\Laravel\get;

it('renders a registered story by slug', function (string $slug, Closure $seed, string $component) {
    $seed();

    get("/stories/{$slug}")->assertOk()->assertInertia(fn ($page) => $page
        ->component($component)
        ->has('og')
        ->where('story.hasData', true)
    );
})->with([
    'fuel' => ['fuel', function () {
        Fuel::factory()->create(['occurred_at' => '2024-01-01 09:00:00', 'odometer' => 10000, 'litres' => 40]);
        Fuel::factory()->create(['occurred_at' => '2024-02-01 09:00:00', 'odometer' => 10300, 'litres' => 40]);
    }, 'Stories/Fuel'],
    'food' => ['food', function () {
        Calorie::factory()->create(['occurred_at' => '2024-01-01 00:00:00', 'calories' => 600]);
        Calorie::factory()->create(['occurred_at' => '2024-01-02 00:00:00', 'calories' => 700]);
    }, 'Stories/Food'],
    'flights' => ['flights', function () {
        Flight::factory()->create(['occurred_at' => '2023-05-15 09:00:00', 'distance' => Distance::fromMiles(600)]);
        Flight::factory()->create(['occurred_at' => '2023-05-17 09:00:00', 'distance' => Distance::fromMiles(600)]);
    }, 'Stories/Flights'],
]);

it('404s an unknown story slug', function () {
    get('/stories/not-a-story')->assertNotFound();
});

it('lists every data story on the archive', function () {
    get('/stories')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Stories/Index')
        ->has('og')
        ->has('stories', 3)
        ->where('stories.0.slug', 'fuel')
        ->where('stories.1.slug', 'food')
        ->where('stories.2.slug', 'flights')
        ->has('stories.0.title')
        ->has('stories.0.accent')
    );
});
