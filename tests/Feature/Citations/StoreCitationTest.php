<?php

use App\Actions\Citations\StoreCitation;
use App\Data\CitationData;
use App\Enums\ResponseKind;
use App\Jobs\FetchCitationFor;
use App\Models\Citation;
use App\Models\Note;
use Illuminate\Support\Facades\Queue;

function found(array $overrides = []): CitationData
{
    return new CitationData(...array_merge([
        'url' => 'https://example.com/post',
        'site' => 'example.com',
        'title' => 'A title',
        'authorName' => 'Jo Bloggs',
        'authorPhotoUrl' => null,
        'excerpt' => 'What they wrote.',
        'publishedAt' => now()->subDay(),
        'publishedTimezone' => '+01:00',
    ], $overrides));
}

it('stores a new citation and points the reply at it', function () {
    $note = Note::factory()->create();

    $citation = app(StoreCitation::class)(found(), $note);

    expect($citation->title)->toBe('A title')
        ->and($note->fresh()->citation_id)->toBe($citation->id);
});

// The whole point of keeping a copy: a page that has gone quiet cannot erase it.
it('never blanks a stored field with a thinner fetch', function () {
    app(StoreCitation::class)(found());

    app(StoreCitation::class)(found(['title' => null, 'excerpt' => null, 'authorName' => null]));

    $citation = Citation::query()->sole();

    expect($citation->title)->toBe('A title')
        ->and($citation->excerpt)->toBe('What they wrote.')
        ->and($citation->author_name)->toBe('Jo Bloggs');
});

it('updates a field when the fetch found a new value', function () {
    app(StoreCitation::class)(found());
    app(StoreCitation::class)(found(['title' => 'A better title']));

    expect(Citation::query()->sole()->title)->toBe('A better title');
});

it('links a reply to a citation that is already stored for its URL', function () {
    Queue::fake();
    $citation = Citation::factory()->create(['url' => 'https://example.com/post']);

    $note = Note::factory()->create(['response_kind' => ResponseKind::Reply, 'response_url' => 'https://example.com/post']);

    expect($note->fresh()->citation_id)->toBe($citation->id);
    Queue::assertNotPushed(FetchCitationFor::class);
});

it('queues a fetch for a reply to a post nothing has stored yet', function () {
    Queue::fake();

    Note::factory()->create(['response_kind' => ResponseKind::Reply, 'response_url' => 'https://example.com/new']);

    Queue::assertPushed(FetchCitationFor::class);
});

it('drops the old citation and quote when the reply is pointed somewhere else', function () {
    Queue::fake();
    $old = Citation::factory()->create(['url' => 'https://example.com/old']);

    $note = Note::factory()->create([
        'response_kind' => ResponseKind::Reply,
        'response_url' => 'https://example.com/old',
        'response_quote' => 'A passage from the old post.',
    ]);

    $note->update(['response_url' => 'https://example.com/elsewhere']);

    expect($note->fresh()->citation_id)->toBeNull()
        ->and($note->fresh()->response_quote)->toBeNull();
});

it('never queues a fetch for a reply to one of my own entries', function () {
    Queue::fake();
    $target = Note::factory()->create();

    Note::factory()->create(['response_kind' => ResponseKind::Reply, 'response_url' => config('app.url').$target->url()]);

    Queue::assertNotPushed(FetchCitationFor::class);
});
