<?php

use App\Models\Article;
use App\Models\Mention;
use App\Models\Note;
use App\Models\Sleep;
use App\Support\PortableText;

use function Pest\Laravel\get;

/**
 * Linking to one of my own entries records a mention on it. Derived from the
 * body on save, so writing the link is the whole authoring step.
 */

/** A paragraph whose only link points at $href, the shape the editor writes. */
function linkedTo(string $href, string $label = 'that entry'): array
{
    $key = 'link-1';

    return [[
        '_type' => 'block',
        '_key' => 'block-1',
        'style' => 'normal',
        'markDefs' => [['_key' => $key, '_type' => 'link', 'href' => $href]],
        'children' => [
            PortableText::span('See '),
            PortableText::span($label, [$key]),
        ],
    ]];
}

it('records a mention on the entry a note links to', function () {
    $sleep = Sleep::factory()->create();

    $note = Note::factory()->create(['content' => linkedTo($sleep->url())]);

    expect($sleep->mentions()->count())->toBe(1)
        ->and($sleep->mentions()->first()->source->is($note))->toBeTrue();
});

// A link typed by hand is a path; one pasted from the address bar is the whole
// address. Both name the same page and must reach the same row.
it('records a mention when the link is written as a full url', function () {
    $sleep = Sleep::factory()->create();

    Note::factory()->create([
        'content' => PortableText::fromPlainText('Slept well: '.rtrim(config('app.url'), '/').$sleep->url()),
    ]);

    expect($sleep->mentions()->count())->toBe(1);
});

// Both spellings of the same entry in one body. They collapse to one path
// before anything is resolved, so the pair never reaches the unique constraint.
it('records one mention however many times an entry is linked', function () {
    $sleep = Sleep::factory()->create();

    $blocks = [
        ...linkedTo($sleep->url(), 'first'),
        ...linkedTo(rtrim(config('app.url'), '/').$sleep->url(), 'again'),
    ];
    $blocks[1]['_key'] = 'block-2';
    $blocks[1]['markDefs'][0]['_key'] = 'link-2';
    $blocks[1]['children'][1]['marks'] = ['link-2'];

    Note::factory()->create(['content' => $blocks]);

    expect($sleep->mentions()->count())->toBe(1);
});

it('removes the mention when the link is taken out again', function () {
    $sleep = Sleep::factory()->create();
    $note = Note::factory()->create(['content' => linkedTo($sleep->url())]);

    $note->update(['content' => PortableText::fromPlainText('Never mind.')]);

    expect($sleep->mentions()->count())->toBe(0);
});

// A draft is not a page anyone can read, so a link to it has nowhere to land.
it('records nothing for a link to an unpublished article', function () {
    $article = Article::factory()->create(['published' => false]);

    Note::factory()->create(['content' => linkedTo($article->url())]);

    expect(Mention::query()->count())->toBe(0);
});

// Unpublishing hides the link along with the page it was written on.
it('removes a source\'s mentions when it stops being published', function () {
    $sleep = Sleep::factory()->create();
    $article = Article::factory()->create([
        'published' => true,
        'content' => linkedTo($sleep->url()),
    ]);

    expect($sleep->mentions()->count())->toBe(1);

    $article->update(['published' => false]);

    expect($sleep->mentions()->count())->toBe(0);
});

// An article, not a note: a note's slug is derived from its content, so
// rewriting the body moves the URL and the link would miss for the wrong reason.
it('records nothing for an entry that links to itself', function () {
    $article = Article::factory()->create(['published' => true]);

    $article->update(['content' => linkedTo($article->url())]);

    expect(Mention::query()->count())->toBe(0);
});

// A note's first eighty characters are not a name, so the byline says what the
// source is and when, rather than quoting the top of it. Not possessive: the
// byline already names who wrote it.
it('names a note source by what it is rather than quoting its first words', function () {
    $sleep = Sleep::factory()->create();
    Note::factory()->create([
        'occurred_at' => now()->setDate(now()->year, 3, 14),
        'content' => linkedTo($sleep->url()),
    ]);

    get($sleep->url())
        ->assertInertia(fn ($page) => $page->where('conversation.responses.0.title', 'a note from 14 March'));
});

it('sends the title for a mention that came from an article', function () {
    $sleep = Sleep::factory()->create();
    $article = Article::factory()->create([
        'published' => true,
        'content' => linkedTo($sleep->url()),
    ]);

    get($sleep->url())
        ->assertInertia(fn ($page) => $page->where('conversation.responses.0.title', $article->title));
});

it('shows the mention in the linked entry\'s conversation', function () {
    $sleep = Sleep::factory()->create();
    Note::factory()->create(['content' => linkedTo($sleep->url())]);

    get($sleep->url())
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('conversation.responses.0.kind', 'mention-internal')
            ->where('conversation.responses.0.authorName', config('feed.author_name'))
            // A path, not a host: the source is a page on this site, so there
            // is no "via somewhere-else" to close the byline with.
            ->where('conversation.responses.0.sourceHost', null)
            ->where('conversation.responses.0.body', null));
});
