<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the events dataset from data/events.csv', function () {
    $this->artisan('import:csv', [
        'file' => base_path('data/events.csv'),
        'type' => 'event',
    ])->assertSuccessful();

    expect(Event::count())->toBeGreaterThan(80);

    // A multi-day conference with real daily times, so it is NOT all-day; the
    // ends_at still spans to the closing day.
    $wceu = Event::where('name', 'WordCamp Europe 2025')->firstOrFail();
    expect($wceu->all_day)->toBeFalse()
        ->and($wceu->ends_at)->not->toBeNull()
        ->and($wceu->tagNames())->toContain('Conference')
        ->and($wceu->timezone)->toBe('Europe/Zurich');

    // The category now arrives as a tag from the CSV `tags` column.
    $panto = Event::whereHas('tags', fn ($query) => $query->where('slug', 'theatre'))
        ->whereNotNull('organiser')
        ->where('organiser', 'Sanderstead Dramatic Club')
        ->first();
    expect($panto)->not->toBeNull();

    $withSeat = Event::whereNotNull('meta')->get()
        ->first(fn (Event $e): bool => ! empty($e->meta['seat'] ?? null));
    expect($withSeat)->not->toBeNull();
});
