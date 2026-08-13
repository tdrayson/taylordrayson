<?php

use App\Models\Article;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('refuses a create without a newly required field', function (string $type, array $payload, string $missing) {
    $this->postJson("/entries/{$type}", $payload)->assertJsonValidationErrors($missing);
})->with([
    'event date' => ['event', ['name' => 'A gig', 'tags' => ['music']], 'occurred_at'],
    'event category' => ['event', ['name' => 'A gig', 'occurred_at' => '2026-08-13 19:00:00'], 'tags'],
    'book author' => ['book', ['title' => 'A book', 'occurred_at' => '2026-08-13 19:00:00'], 'meta.author'],
    'appearance show' => ['appearance', ['title' => 'A talk', 'occurred_at' => '2026-08-13 19:00:00', 'type' => 'podcast'], 'show_name'],
    'appearance kind' => ['appearance', ['title' => 'A talk', 'occurred_at' => '2026-08-13 19:00:00', 'show_name' => 'A show'], 'type'],
    'project description' => ['project', ['title' => 'A project', 'status' => 'active'], 'description'],
    'project status' => ['project', ['title' => 'A project', 'description' => 'A summary'], 'status'],
    'flight airline' => ['flight', ['occurred_at' => '2026-08-13 09:00:00', 'origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'flight_number' => 'BA117'], 'airline_icao'],
    'flight number' => ['flight', ['occurred_at' => '2026-08-13 09:00:00', 'origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'airline_icao' => 'BAW'], 'flight_number'],
    'fuel date' => ['fuel', ['cost' => 51.87, 'price_per_litre' => 1.599], 'occurred_at'],
]);

it('still allows an update that touches one field', function () {
    // required applies only on create, so an edit may send a single field.
    $article = Article::factory()->create(['title' => 'Before']);

    $this->patch("/entries/article/{$article->id}", ['title' => 'After'])->assertRedirect();

    expect($article->fresh()->title)->toBe('After');
});
