<?php

use App\Models\Event;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('makes events taggable and surfaces them on the cross-type tag page', function () {
    $event = Event::factory()->create(['name' => 'Hamilton', 'type' => 'musical', 'occurred_at' => now()->subDay()]);
    $note = Note::factory()->create(['content' => 'Loved the show', 'occurred_at' => now()->subDays(2)]);
    $event->syncTagNames(['Theatre']);
    $note->syncTagNames(['Theatre']);

    // The same tag spans an event and a note on one cross-type feed.
    get('/tags/theatre')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Tag')
        ->where('name', 'Theatre')
        ->where('groups', function ($groups) {
            $items = collect($groups)->flatMap(fn ($group) => $group['items']);

            // Both the event and the note resolve onto the one cross-type feed.
            return $items->pluck('title')->contains('Hamilton')
                && $items->pluck('body')->contains('Loved the show');
        })
    );
});

it('backfills event category tags from the legacy type column, additively and idempotently', function () {
    $musical = Event::factory()->create(['type' => 'musical', 'occurred_at' => now()->subDay()]);
    $theatre = Event::factory()->create(['type' => 'theatre', 'occurred_at' => now()->subDays(2)]);

    artisan('events:tag-from-type')->assertSuccessful();

    expect($musical->fresh()->tagNames())->toContain('Musical')
        ->and($theatre->fresh()->tagNames())->toContain('Theatre');

    // Additive + idempotent: a manually-added tag survives a re-run.
    $musical->syncTagNames(['Musical', 'Family']);
    artisan('events:tag-from-type')->assertSuccessful();

    expect($musical->fresh()->tagNames())->toContain('Musical')->toContain('Family');
});
