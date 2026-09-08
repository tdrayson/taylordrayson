<?php

use App\Enums\ResponseKind;
use App\Enums\RsvpValue;
use App\Models\Article;
use App\Models\Note;
use App\Support\PostType;

/**
 * What a post is, derived from the properties it carries rather than from the
 * column it stores, so the cards and the markup cannot disagree.
 */
it('is a plain note until it points at something', function () {
    $note = Note::factory()->create();

    expect($note->responseKind())->toBeNull()
        ->and($note->isResponse())->toBeFalse();
});

it('is the kind it declares once it has a target', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/post',
    ]);

    expect($note->responseKind())->toBe(ResponseKind::Like);
});

// A kind with nothing to point at claims a response to nowhere, so the claim
// is dropped rather than published.
it('forgets the kind when there is no url to respond to', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => null,
    ]);

    expect($note->fresh()->response_kind)->toBeNull()
        ->and($note->responseKind())->toBeNull();
});

// Post type discovery checks the answer before the property, or every RSVP
// would read as an ordinary reply.
it('is an rsvp rather than a reply when it answers one', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Rsvp,
        'response_url' => 'https://example.com/event',
        'rsvp_value' => RsvpValue::Yes,
    ]);

    expect(PostType::of($note))->toBe(ResponseKind::Rsvp)
        ->and($note->rsvp_value)->toBe(RsvpValue::Yes);
});

// p-rsvp is the property the type is named for. Without a value there is no
// RSVP to publish, whatever the column says.
it('is nothing at all when an rsvp has no answer', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Rsvp,
        'response_url' => 'https://example.com/event',
        'rsvp_value' => null,
    ]);

    expect($note->responseKind())->toBeNull();
});

// Switching kind after answering would otherwise leave the answer behind, and
// a stray p-rsvp is what a parser reads the whole post's type from.
it('drops an answer that belongs to a kind it is no longer', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Rsvp,
        'response_url' => 'https://example.com/event',
        'rsvp_value' => RsvpValue::Maybe,
    ]);

    $note->update(['response_kind' => ResponseKind::Like]);

    expect($note->fresh()->rsvp_value)->toBeNull()
        ->and($note->responseKind())->toBe(ResponseKind::Like);
});

it('names the microformats property each kind publishes', function () {
    expect(ResponseKind::Reply->property())->toBe('in-reply-to')
        ->and(ResponseKind::Like->property())->toBe('like-of')
        ->and(ResponseKind::Repost->property())->toBe('repost-of')
        // An RSVP is an in-reply-to that also answers.
        ->and(ResponseKind::Rsvp->property())->toBe('in-reply-to');
});

it('treats every kind but a reply as a gesture', function () {
    expect(ResponseKind::Reply->isGesture())->toBeFalse()
        ->and(ResponseKind::Like->isGesture())->toBeTrue()
        ->and(ResponseKind::Repost->isGesture())->toBeTrue()
        ->and(ResponseKind::Rsvp->isGesture())->toBeTrue();
});

// The context card is what makes the post read as an answer, and its property
// class is what makes a parser agree.
it('carries the reply context to the entry page', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => 'https://aaronparecki.com/2018/06/30/11/your-first-webmention',
    ]);

    Pest\Laravel\get($note->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('entry.response.property', 'in-reply-to')
            ->where('entry.response.label', 'Replied to')
            ->where('entry.response.host', 'aaronparecki.com')
            // Somebody else's post is a URL on a host, never a card of ours.
            ->where('entry.response.preview', null));
});

it('draws one of my own entries as its own card rather than a host', function () {
    $article = Article::factory()->create(['published' => true]);

    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => rtrim(config('app.url'), '/').$article->url(),
    ]);

    Pest\Laravel\get($note->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('entry.response.preview.title', $article->title)
            ->where('entry.response.host', null));
});

it('sends no response context for a plain note', function () {
    $note = Note::factory()->create();

    Pest\Laravel\get($note->url())
        ->assertInertia(fn ($page) => $page->where('entry.response', null));
});
