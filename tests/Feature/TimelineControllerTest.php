<?php

use App\Models\Activity;
use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;

it('returns 200 for the homepage', function () {
    $response = $this->get('/');

    $response->assertOk();
});

it('displays the timeline heading', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Timeline');
});

it('shows timeline entries in reverse chronological order', function () {
    Activity::factory()->create([
        'name' => 'Oldest Activity',
        'occurred_at' => now()->subDays(3),
    ]);

    Activity::factory()->create([
        'name' => 'Middle Activity',
        'occurred_at' => now()->subDays(2),
    ]);

    Activity::factory()->create([
        'name' => 'Newest Activity',
        'occurred_at' => now()->subDay(),
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSeeInOrder([
        'Newest Activity',
        'Middle Activity',
        'Oldest Activity',
    ]);
});

it('shows calorie entries in the timeline', function () {
    Calorie::factory()->create([
        'occurred_at' => now(),
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('calorie');
});

it('paginates timeline entries', function () {
    Activity::factory()->count(55)->create([
        'occurred_at' => fn () => fake()->dateTimeBetween('-6 months'),
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('page=2');
});

it('renders different card types', function () {
    Activity::factory()->create([
        'name' => 'Morning Park Run',
        'occurred_at' => now()->subHour(),
    ]);

    Flight::factory()->create([
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'occurred_at' => now()->subHours(2),
    ]);

    Media::factory()->create([
        'title' => 'The Shawshank Redemption',
        'type' => 'film',
        'occurred_at' => now()->subHours(3),
    ]);

    Note::factory()->create([
        'content' => 'A unique test note for verification',
        'occurred_at' => now()->subHours(4),
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Morning Park Run');
    $response->assertSee('LHR');
    $response->assertSee('JFK');
    $response->assertSee('The Shawshank Redemption');
    $response->assertSee('A unique test note for verification');
});

it('shows date grouping headers', function () {
    Activity::factory()->create([
        'occurred_at' => now()->subDay(),
    ]);

    Activity::factory()->create([
        'occurred_at' => now()->subDays(3),
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee(now()->subDay()->format('l j F Y'));
    $response->assertSee(now()->subDays(3)->format('l j F Y'));
});
