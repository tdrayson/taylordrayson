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

it('shows distance for any activity type that records one, without a cardio allow-list', function () {
    // 'kayak' is in no hardcoded cardio list, but it has a distance, so the card
    // must still surface it. The subtitle is data-driven, not type-driven.
    $activity = Activity::create([
        'occurred_at' => now(),
        'type' => 'kayak',
        'distance' => 5000,
        'duration' => 1800,
    ]);

    $card = $activity->card();

    expect($card['subtitle'])->toContain('mi')
        ->and($card['subtitleTokens'])->toContain(['t' => 'dist', 'm' => 5000, 'p' => 1]);
});

it('shows a strength subtitle whenever sets exist, regardless of type', function () {
    // No type in any list: the presence of sets alone drives the strength card.
    $activity = Activity::create([
        'occurred_at' => now(),
        'type' => 'crossfit',
        'duration' => 1800,
        'meta' => ['sets' => [['exercise' => 'Snatch', 'reps' => 3, 'weight_kg' => 60]]],
    ]);

    $card = $activity->card();

    expect($card['subtitle'])->toContain('exercise')
        ->and($card['subtitleTokens'])->toContain(['t' => 'text', 'v' => '1 exercise']);
});
