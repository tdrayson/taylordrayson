<?php

use App\Models\Event;
use App\Models\TimelineEntry;

it('mirrors ends_at from a multi-day event onto its timeline entry', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);

    expect($event->timelineEntry->ends_at->toDateString())->toBe('2022-06-04');
});

it('leaves ends_at null for single-day entries', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => null,
    ]);

    expect($event->timelineEntry->ends_at)->toBeNull();
});

it('coveringDate matches every day within a multi-day range', function () {
    Event::factory()->create(['occurred_at' => '2022-06-02 09:00:00', 'ends_at' => '2022-06-04 18:00:00']);

    expect(TimelineEntry::query()->coveringDate('2022-06-03')->count())->toBe(1)
        ->and(TimelineEntry::query()->coveringDate('2022-06-05')->count())->toBe(0);
});

it('coveringAnniversary matches a mid-range month-day', function () {
    Event::factory()->create(['occurred_at' => '2022-06-02 09:00:00', 'ends_at' => '2022-06-04 18:00:00']);

    expect(TimelineEntry::query()->coveringAnniversary('06-03')->count())->toBe(1)
        ->and(TimelineEntry::query()->coveringAnniversary('06-05')->count())->toBe(0);
});
