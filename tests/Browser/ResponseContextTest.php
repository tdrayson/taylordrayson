<?php

use App\Enums\ResponseKind;
use App\Enums\RsvpValue;
use App\Models\Note;

/**
 * The property class is the whole point of the context card: without it a
 * parser sees a post that happens to contain a link, not a reply. Asserted on
 * the rendered DOM, since the class exists only once the page has drawn.
 */
it('marks the target link with the property its kind publishes', function () {
    $note = Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'response_kind' => ResponseKind::Reply,
        'response_url' => 'https://example.com/a-post',
    ]);

    visit($note->url())
        ->assertPresent('a.h-cite.u-in-reply-to[href="https://example.com/a-post"]')
        ->assertNoJavascriptErrors();
});

it('publishes a like as like-of rather than a reply', function () {
    $note = Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'content' => [],
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/liked',
    ]);

    visit($note->url())->assertPresent('a.h-cite.u-like-of[href="https://example.com/liked"]');
});

// An RSVP is an in-reply-to plus an answer, and the answer is the half that
// makes it an RSVP to a parser.
it('publishes an rsvp answer as a p-rsvp value beside the reply property', function () {
    $note = Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'response_kind' => ResponseKind::Rsvp,
        'response_url' => 'https://example.com/event',
        'rsvp_value' => RsvpValue::Yes,
    ]);

    visit($note->url())
        ->assertPresent('a.h-cite.u-in-reply-to[href="https://example.com/event"]')
        ->assertPresent('data.p-rsvp[value="yes"]');
});

it('draws no context card on a note that answers nobody', function () {
    $note = Note::factory()->create(['occurred_at' => now()->subHour()]);

    visit($note->url())->assertMissing('a.h-cite');
});
