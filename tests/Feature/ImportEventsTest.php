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

    $wceu = Event::where('name', 'WordCamp Europe 2025')->firstOrFail();
    expect($wceu->all_day)->toBeTrue()
        ->and($wceu->ends_at)->not->toBeNull()
        ->and($wceu->type)->toBe('conference')
        ->and($wceu->timezone)->toBe('Europe/Zurich');

    $panto = Event::where('type', 'theatre')
        ->whereNotNull('organiser')
        ->where('organiser', 'Sanderstead Dramatic Club')
        ->first();
    expect($panto)->not->toBeNull();

    $withSeat = Event::whereNotNull('meta')->get()
        ->first(fn (Event $e): bool => ! empty($e->meta['seat'] ?? null));
    expect($withSeat)->not->toBeNull();
});
