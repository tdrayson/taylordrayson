<?php

use App\Models\Event;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('makes events taggable and surfaces them on the cross-type tag page', function () {
    $event = Event::factory()->create(['name' => 'Hamilton', 'occurred_at' => now()->subDay()]);
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
