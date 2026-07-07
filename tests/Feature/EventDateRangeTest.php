<?php

use App\Models\Event;

it('returns a range for a multi-day event', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    expect($event->dateRange())->toMatchArray([
        'days' => 3,
        'label' => '2-4 Jun 2022',
    ]);
});

it('returns null for a single-day event', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => null,
    ]);

    expect($event->dateRange())->toBeNull();
});

it('returns null when ends_at is the same day', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 18:00:00',
        'ends_at' => '2022-06-02 22:00:00',
    ]);

    expect($event->dateRange())->toBeNull();
});

it('does not mutate occurred_at when building the card range', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    $card = $event->card();

    expect($card['range']['days'])->toBe(3)
        ->and($card['occurred_at']->format('H:i'))->toBe('09:00')
        ->and($event->occurred_at->format('H:i'))->toBe('09:00');
});
