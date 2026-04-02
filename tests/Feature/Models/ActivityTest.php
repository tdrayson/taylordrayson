<?php

use App\Models\Activity;
use App\Models\TimelineEntry;

it('can create an activity with factory-like attributes', function () {
    $activity = Activity::create([
        'occurred_at' => now(),
        'type' => 'run',
        'duration' => 1800,
    ]);

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->exists)->toBeTrue();
});

it('creating an activity creates a timeline entry', function () {
    $activity = Activity::create([
        'occurred_at' => now(),
        'type' => 'run',
        'duration' => 1800,
    ]);

    expect(TimelineEntry::count())->toBe(1)
        ->and($activity->timelineEntry)->not->toBeNull();
});

it('the timeline entry occurred_at matches the activity occurred_at', function () {
    $occurredAt = now()->startOfHour();

    $activity = Activity::create([
        'occurred_at' => $occurredAt,
        'type' => 'run',
        'duration' => 1800,
    ]);

    expect($activity->timelineEntry->occurred_at->toDateTimeString())
        ->toBe($occurredAt->toDateTimeString());
});

it('updating activity occurred_at updates the timeline entry occurred_at', function () {
    $activity = Activity::create([
        'occurred_at' => now()->subDay(),
        'type' => 'run',
        'duration' => 1800,
    ]);

    $newDate = now()->startOfHour();
    $activity->update(['occurred_at' => $newDate]);

    expect($activity->timelineEntry->fresh()->occurred_at->toDateTimeString())
        ->toBe($newDate->toDateTimeString());
});

it('deleting an activity deletes its timeline entry', function () {
    $activity = Activity::create([
        'occurred_at' => now(),
        'type' => 'run',
        'duration' => 1800,
    ]);

    expect(TimelineEntry::count())->toBe(1);

    $activity->delete();

    expect(TimelineEntry::count())->toBe(0);
});

it('card returns expected array shape', function () {
    $activity = Activity::create([
        'occurred_at' => now(),
        'type' => 'run',
        'duration' => 1800,
    ]);

    $card = $activity->card();

    expect($card)->toHaveKeys([
        'type',
        'icon',
        'title',
        'subtitle',
        'occurred_at',
        'accent',
        'meta',
    ]);
});
