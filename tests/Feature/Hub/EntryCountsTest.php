<?php

use App\Models\Food;
use App\Models\Note;
use App\Queries\Hub\EntryCounts;

it('counts a day of food as one entry, not one per item', function () {
    Food::factory()->count(6)->create(['occurred_at' => '2026-08-26 12:00:00']);

    $food = collect(app(EntryCounts::class)())->firstWhere('type', 'food');

    expect($food->count)->toBe(1)->and($food->synced)->toBeTrue();
});

it('puts a hand-written type in its own group', function () {
    Note::factory()->create(['occurred_at' => now()->subHour()]);

    $note = collect(app(EntryCounts::class)())->firstWhere('type', 'note');

    expect($note->count)->toBe(1)->and($note->synced)->toBeFalse();
});

it('lists every dataset even when it has no entries', function () {
    expect(app(EntryCounts::class)())->toHaveCount(15);
});

it('renders the payload the component reads', function () {
    Note::factory()->create(['occurred_at' => now()->subHour()]);

    $note = collect(app(EntryCounts::class)())->firstWhere('type', 'note');

    expect($note->toArray())->toHaveKeys(['label', 'icon', 'count', 'lag', 'synced'])
        ->and($note->toArray()['label'])->toBe('Notes');
});
