<?php

use App\Models\Calorie;
use App\Models\TimelineEntry;

it('creating the first calorie for a date creates one timeline entry', function () {
    Calorie::create([
        'occurred_at' => now(),
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    expect(TimelineEntry::count())->toBe(1);
});

it('creating a second calorie for the same date does NOT create a second timeline entry', function () {
    $date = now()->startOfDay()->addHours(8);

    Calorie::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    Calorie::create([
        'occurred_at' => $date->copy()->addHours(4),
        'name' => 'Sandwich',
        'meal' => 'lunch',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 450,
    ]);

    expect(TimelineEntry::count())->toBe(1);
});

it('the timeline entry points to the first calorie row (lowest ID)', function () {
    $date = now()->startOfDay()->addHours(8);

    $first = Calorie::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    Calorie::create([
        'occurred_at' => $date->copy()->addHours(4),
        'name' => 'Sandwich',
        'meal' => 'lunch',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 450,
    ]);

    $entry = TimelineEntry::first();

    expect($entry->timelineable_id)->toBe($first->id);
});

it('the timeline entry occurred_at is set to noon on that date', function () {
    $date = now()->startOfDay()->addHours(8);

    Calorie::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    $entry = TimelineEntry::first();

    expect($entry->occurred_at->format('H:i:s'))->toBe('12:00:00')
        ->and($entry->occurred_at->toDateString())->toBe($date->toDateString());
});

it('deleting the referenced calorie updates the timeline entry to point to the next row', function () {
    $date = now()->startOfDay()->addHours(8);

    $first = Calorie::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    $second = Calorie::create([
        'occurred_at' => $date->copy()->addHours(4),
        'name' => 'Sandwich',
        'meal' => 'lunch',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 450,
    ]);

    $first->delete();

    $entry = TimelineEntry::first();

    expect($entry)->not->toBeNull()
        ->and($entry->timelineable_id)->toBe($second->id);
});

it('deleting all calories for a date deletes the timeline entry', function () {
    $date = now()->startOfDay()->addHours(8);

    $calorie = Calorie::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    expect(TimelineEntry::count())->toBe(1);

    $calorie->delete();

    expect(TimelineEntry::count())->toBe(0);
});

it('calories on different dates create separate timeline entries', function () {
    Calorie::create([
        'occurred_at' => now()->startOfDay()->addHours(8),
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    Calorie::create([
        'occurred_at' => now()->addDay()->startOfDay()->addHours(8),
        'name' => 'Porridge',
        'meal' => 'breakfast',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 350,
    ]);

    expect(TimelineEntry::count())->toBe(2);
});
