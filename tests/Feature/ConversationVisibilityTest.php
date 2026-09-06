<?php

use App\Models\Activity;
use App\Models\Note;

use function Pest\Laravel\get;

/**
 * Whether an entry ends with a place to respond. Every type accepts responses;
 * only some ask for one when nobody has left any.
 */
it('leaves a quiet record without an empty response block', function () {
    $activity = Activity::factory()->create();

    get($activity->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('conversation', null));
});

it('still asks for a response under something written, with nothing there yet', function () {
    $note = Note::factory()->create();

    get($note->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('conversation.reactions'));
});

it('shows the block on a record once somebody has responded', function () {
    $activity = Activity::factory()->create();
    $activity->reactions()->create(['type' => 'love', 'identity_key' => str_repeat('a', 64)]);

    get($activity->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('conversation.reactions'));
});
