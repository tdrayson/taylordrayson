<?php

use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Note;
use App\Models\TimelineEntry;

it('creating a Flight creates a timeline entry', function () {
    $flight = Flight::create([
        'occurred_at' => now(),
        'flight_number' => 'BA123',
        'airline_iata' => 'BA',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
    ]);

    expect($flight->timelineEntry)->not->toBeNull()
        ->and(TimelineEntry::count())->toBe(1);
});

it('creating a Media creates a timeline entry', function () {
    $media = Media::create([
        'occurred_at' => now(),
        'type' => 'film',
        'title' => 'Inception',
    ]);

    expect($media->timelineEntry)->not->toBeNull()
        ->and(TimelineEntry::count())->toBe(1);
});

it('creating a Checkin creates a timeline entry', function () {
    $checkin = Checkin::create([
        'occurred_at' => now(),
        'venue_name' => 'Starbucks',
    ]);

    expect($checkin->timelineEntry)->not->toBeNull()
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
        'airline_iata' => 'BA',
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
