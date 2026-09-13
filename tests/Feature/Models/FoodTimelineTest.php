<?php

use App\Models\Food;
use App\Models\TimelineEntry;

it('creating the first food item for a date creates one timeline entry', function () {
    Food::create([
        'occurred_at' => now(),
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    expect(TimelineEntry::count())->toBe(1);
});

it('creating a second food item for the same date does NOT create a second timeline entry', function () {
    $date = now()->startOfDay()->addHours(8);

    Food::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    Food::create([
        'occurred_at' => $date->copy()->addHours(4),
        'name' => 'Sandwich',
        'meal' => 'lunch',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 450,
    ]);

    expect(TimelineEntry::count())->toBe(1);
});

it('the timeline entry points to the first food row (lowest ID)', function () {
    $date = now()->startOfDay()->addHours(8);

    $first = Food::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    Food::create([
        'occurred_at' => $date->copy()->addHours(4),
        'name' => 'Sandwich',
        'meal' => 'lunch',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 450,
    ]);

    $entry = TimelineEntry::first();

    expect($entry->entry_id)->toBe($first->id);
});

it('the timeline entry occurred_at is set to noon on that date', function () {
    $date = now()->startOfDay()->addHours(8);

    Food::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    $entry = TimelineEntry::first();

    // End of the day the food belongs to: a daily total is only true once the
    // day is done, and the card and its timezone both read that moment.
    expect($entry->occurred_at->format('H:i:s'))->toBe('23:59:59')
        ->and($entry->occurred_at->toDateString())->toBe($date->toDateString());
});

it('deleting the referenced food item updates the timeline entry to point to the next row', function () {
    $date = now()->startOfDay()->addHours(8);

    $first = Food::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    $second = Food::create([
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
        ->and($entry->entry_id)->toBe($second->id);
});

it('deleting all food items for a date deletes the timeline entry', function () {
    $date = now()->startOfDay()->addHours(8);

    $food = Food::create([
        'occurred_at' => $date,
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    expect(TimelineEntry::count())->toBe(1);

    $food->delete();

    expect(TimelineEntry::count())->toBe(0);
});

it('food items on different dates create separate timeline entries', function () {
    Food::create([
        'occurred_at' => now()->startOfDay()->addHours(8),
        'name' => 'Weetabix',
        'meal' => 'breakfast',
        'quantity' => 2,
        'units' => 'serving',
        'calories' => 280,
    ]);

    Food::create([
        'occurred_at' => now()->addDay()->startOfDay()->addHours(8),
        'name' => 'Porridge',
        'meal' => 'breakfast',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 350,
    ]);

    expect(TimelineEntry::count())->toBe(2);
});
