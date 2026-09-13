<?php

use App\Models\Film;
use App\Models\Flight;
use App\Models\Note;
use App\Models\Place;
use App\Models\TimelineEntry;

it('creating a Flight creates a timeline entry', function () {
    $flight = Flight::create([
        'occurred_at' => now(),
        'flight_number' => 'BA123',
        'airline_icao' => 'BAW',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
    ]);

    expect($flight->timelineEntry)->not->toBeNull()
        ->and(TimelineEntry::count())->toBe(1);
});

it('creating a Film creates a timeline entry', function () {
    $film = Film::create([
        'occurred_at' => now(),
        'title' => 'Inception',
    ]);

    expect($film->timelineEntry)->not->toBeNull()
        ->and(TimelineEntry::count())->toBe(1);
});

it('creating a Place creates a timeline entry', function () {
    $place = Place::create([
        'occurred_at' => now(),
        'venue_name' => 'Starbucks',
    ]);

    expect($place->timelineEntry)->not->toBeNull()
        ->and(TimelineEntry::count())->toBe(1);
});

it('creating a Note creates a timeline entry', function () {
    $note = Note::create([
        'occurred_at' => now(),
        'content' => 'A quick thought.',
    ]);

    expect($note->timelineEntry)->not->toBeNull()
        ->and(TimelineEntry::count())->toBe(1);
});

it('deleting a model deletes its timeline entry', function () {
    $flight = Flight::create([
        'occurred_at' => now(),
        'flight_number' => 'BA456',
        'airline_icao' => 'BAW',
        'origin_iata' => 'MAN',
        'destination_iata' => 'CDG',
    ]);

    expect(TimelineEntry::count())->toBe(1);

    $flight->delete();

    expect(TimelineEntry::count())->toBe(0);
});

it('updating occurred_at on a model updates the timeline entry', function () {
    $note = Note::create([
        'occurred_at' => now()->subWeek(),
        'content' => 'Old note.',
    ]);

    $newDate = now()->startOfHour();
    $note->update(['occurred_at' => $newDate]);

    expect($note->timelineEntry->fresh()->occurred_at->toDateTimeString())
        ->toBe($newDate->toDateTimeString());
});
