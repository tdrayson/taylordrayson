<?php

use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Fuel;

use function Pest\Laravel\get;

it('renders a registered story by slug', function () {
    Fuel::factory()->create(['occurred_at' => '2024-01-01 09:00:00', 'odometer' => 10000, 'litres' => 40]);
    Fuel::factory()->create(['occurred_at' => '2024-02-01 09:00:00', 'odometer' => 10300, 'litres' => 40]);

    get('/stories/fuel')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Stories/Fuel')
        ->has('og')
        ->where('story.hasData', true)
    );
});

it('404s an unknown story slug', function () {
    get('/stories/not-a-story')->assertNotFound();
});

it('renders the food story by slug', function () {
    Calorie::factory()->create(['occurred_at' => '2024-01-01 00:00:00', 'calories' => 600]);
    Calorie::factory()->create(['occurred_at' => '2024-01-02 00:00:00', 'calories' => 700]);

    get('/stories/food')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Stories/Food')
        ->has('og')
        ->where('story.hasData', true)
    );
});

it('renders the flights story by slug', function () {
    Flight::factory()->create(['occurred_at' => '2023-05-15 09:00:00', 'distance_miles' => 600]);
    Flight::factory()->create(['occurred_at' => '2023-05-17 09:00:00', 'distance_miles' => 600]);

    get('/stories/flights')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Stories/Flights')
        ->has('og')
        ->where('story.hasData', true)
    );
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
