<?php

use App\Actions\BuildResponseContext;
use App\Enums\ResponseKind;
use App\Enums\RsvpValue;
use App\Jobs\FetchResponseTitle;
use App\Jobs\SendWebmentions;
use App\Models\Article;
use App\Models\Note;
use App\Support\OutboundLinks;
use App\Support\PortableText;
use App\Support\PostType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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
            // Somebody else's post leaves the site, so it is not linked as ours.
            ->where('entry.response.internal', false));
});

it('names one of my own entries rather than the site it is already on', function () {
    $article = Article::factory()->create(['published' => true]);

    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => rtrim(config('app.url'), '/').$article->url(),
    ]);

    Pest\Laravel\get($note->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('entry.response.title', $article->title)
            ->where('entry.response.internal', true)
            ->where('entry.response.host', null));
});

it('sends no response context for a plain note', function () {
    $note = Note::factory()->create();

    Pest\Laravel\get($note->url())
        ->assertInertia(fn ($page) => $page->where('entry.response', null));
});

// The whole point of declaring a reply is that the other site hears about it.
it('sends a webmention to the post it replies to', function () {
    Queue::fake();

    Note::factory()->create([
        'content' => [],
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/liked',
    ]);

    Queue::assertPushed(SendWebmentions::class);
});

it('counts the target among the urls a send goes out to', function () {
    $note = Note::factory()->create([
        'content' => [],
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/liked',
    ]);

    expect(OutboundLinks::for($note))->toContain('https://example.com/liked');
});

// One of mine is recorded as a mention, not announced over HTTP to myself.
it('sends nothing when the target is one of my own entries', function () {
    $article = Article::factory()->create(['published' => true]);

    $note = Note::factory()->create([
        'content' => [],
        'response_kind' => ResponseKind::Reply,
        'response_url' => rtrim(config('app.url'), '/').$article->url(),
    ]);

    expect(OutboundLinks::for($note))->toBe([]);
});

// What the byline says the target is called, read off the page rather than
// guessed from its URL.
it('reads the target name from its h-entry', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<div class="h-entry"><h1 class="p-name">Sending your First Webmention</h1></div>',
        ),
    ]);

    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => 'https://example.com/a-post',
    ]);

    (new FetchResponseTitle($note))->handle();

    expect($note->fresh()->response_title)->toBe('Sending your First Webmention');
});

// An RSVP target is an event, and a site that marks one up names it there.
it('reads the target name from its h-event', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<div class="h-event"><h1 class="p-name">Homebrew Website Club</h1></div>',
        ),
    ]);

    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Rsvp,
        'response_url' => 'https://example.com/an-event',
        'rsvp_value' => RsvpValue::Yes,
    ]);

    (new FetchResponseTitle($note))->handle();

    expect($note->fresh()->response_title)->toBe('Homebrew Website Club');
});

it('falls back to what a page without microformats calls itself', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><head><title>A Plain Old Page</title></head><body><p>Words.</p></body></html>',
        ),
    ]);

    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/plain',
    ]);

    (new FetchResponseTitle($note))->handle();

    expect($note->fresh()->response_title)->toBe('A Plain Old Page');
});

// A name belongs to the URL it was read from.
it('drops the fetched name when the post is pointed somewhere else', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Like,
        'response_url' => 'https://example.com/one',
        'response_title' => 'The First One',
    ]);

    $note->update(['response_url' => 'https://example.com/two']);

    expect($note->fresh()->response_title)->toBeNull();
});

// A note of mine has no name, and its opening words are not one. The date is
// what makes "my note" specific enough to be worth clicking.
it('calls one of my own notes a note from the day it was written', function () {
    $target = Note::factory()->create([
        'occurred_at' => now()->setDate(now()->year, 3, 14),
        'content' => PortableText::fromPlainText('Some passing thought.'),
    ]);

    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => rtrim(config('app.url'), '/').$target->url(),
    ]);

    expect(app(BuildResponseContext::class)($note)->fullTitle())->toBe('my note from 14 March');
});

it('says the site after the name, so only the name is the p-name', function () {
    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => 'https://example.com/a-post',
        'response_title' => 'A Real Title',
    ]);

    $context = app(BuildResponseContext::class)($note);

    expect($context->title)->toBe('A Real Title')
        ->and($context->host)->toBe('example.com')
        ->and($context->fullTitle())->toBe('A Real Title on example.com');
});
