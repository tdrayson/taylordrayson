<?php

use App\Content\ContentEntry;
use App\Content\ContentRepository;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
    $this->repo = app(ContentRepository::class);
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

// ---------------------------------------------------------------------------
// articles()
// ---------------------------------------------------------------------------

it('returns article cards matching the legacy card shape', function () {
    Entry::make()->collection('articles')->slug('p1')
        ->date('2024-05-01')->data(['title' => 'P1', 'excerpt' => 'sub'])->save();

    $entry = $this->repo->articles()->first(fn (ContentEntry $e) => $e->slug() === 'p1');
    $card = $entry->card();

    expect($card)->toMatchArray([
        'type' => 'article',
        'icon' => 'file-text',
        'title' => 'P1',
        'subtitle' => 'sub',
        'accent' => 'article',
        'meta' => [],
    ]);
});

it('articles() excludes draft entries', function () {
    Entry::make()->collection('articles')->slug('draft-article')
        ->date('2024-05-01')->data(['title' => 'Draft'])->published(false)->save();

    $slugs = $this->repo->articles()->map(fn (ContentEntry $e) => $e->slug())->all();

    expect($slugs)->not->toContain('draft-article');
});

it('articles() occurred_at is a Carbon instance', function () {
    Entry::make()->collection('articles')->slug('dated-article')
        ->date('2024-06-15')->data(['title' => 'Dated'])->save();

    $entry = $this->repo->articles()->first(fn (ContentEntry $e) => $e->slug() === 'dated-article');

    expect($entry->occurredAt())->toBeInstanceOf(Carbon::class);
    expect($entry->occurredAt()->toDateString())->toBe('2024-06-15');
});

// ---------------------------------------------------------------------------
// notes()
// ---------------------------------------------------------------------------

it('returns note cards matching the legacy card shape', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello from a note']]],
    ];

    Entry::make()->collection('notes')->slug('note-42')
        ->date('2024-05-10')->data(['content' => $bardContent])->save();

    $card = $this->repo->notes()->first(fn (ContentEntry $e) => $e->slug() === 'note-42')->card();

    expect($card)->toMatchArray([
        'type' => 'note',
        'icon' => 'message-circle',
        'subtitle' => null,
        'accent' => 'note',
        'meta' => [],
    ]);
    expect($card['title'])->toBe('Hello from a note');
});

it('note card title is truncated via Str::limit(80) matching legacy Note::card()', function () {
    $longText = str_repeat('a', 100);
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $longText]]],
    ];

    Entry::make()->collection('notes')->slug('note-long')
        ->date('2024-05-11')->data(['content' => $bardContent])->save();

    $card = $this->repo->notes()->first(fn (ContentEntry $e) => $e->slug() === 'note-long')->card();

    // Str::limit($text, 80) appends '...' so the output is 83 chars for a 100-char input,
    // matching the exact behaviour of the legacy Note::card() which uses Str::limit(..., 80).
    $expected = Str::limit($longText, 80);
    expect($card['title'])->toBe($expected);
});

it('notes() excludes draft entries', function () {
    Entry::make()->collection('notes')->slug('draft-note')
        ->date('2024-05-12')->data(['content' => []])->published(false)->save();

    $slugs = $this->repo->notes()->map(fn (ContentEntry $e) => $e->slug())->all();

    expect($slugs)->not->toContain('draft-note');
});

// ---------------------------------------------------------------------------
// all()
// ---------------------------------------------------------------------------

it('all() returns both articles and notes', function () {
    Entry::make()->collection('articles')->slug('my-article')
        ->date('2024-05-01')->data(['title' => 'My Article'])->save();
    Entry::make()->collection('notes')->slug('my-note')
        ->date('2024-05-02')->data(['content' => []])->save();

    $types = $this->repo->all()->map(fn (ContentEntry $e) => $e->type())->unique()->sort()->values()->all();

    expect($types)->toContain('article');
    expect($types)->toContain('note');
});

// ---------------------------------------------------------------------------
// findByTypeAndSlug()
// ---------------------------------------------------------------------------

it('findByTypeAndSlug() finds an article by slug', function () {
    Entry::make()->collection('articles')->slug('find-me')
        ->date('2024-05-01')->data(['title' => 'Find Me', 'excerpt' => 'desc'])->save();

    $entry = $this->repo->findByTypeAndSlug('article', 'find-me');

    expect($entry)->toBeInstanceOf(ContentEntry::class);
    expect($entry->type())->toBe('article');
    expect($entry->slug())->toBe('find-me');
});

it('findByTypeAndSlug() finds a note by slug', function () {
    Entry::make()->collection('notes')->slug('note-99')
        ->date('2024-05-05')->data(['content' => []])->save();

    $entry = $this->repo->findByTypeAndSlug('note', 'note-99');

    expect($entry)->toBeInstanceOf(ContentEntry::class);
    expect($entry->type())->toBe('note');
});

it('findByTypeAndSlug() returns null for missing slug', function () {
    $result = $this->repo->findByTypeAndSlug('article', 'no-such-slug');

    expect($result)->toBeNull();
});

// ---------------------------------------------------------------------------
// betweenDates()
// ---------------------------------------------------------------------------

it('betweenDates() returns entries within the inclusive date range', function () {
    Entry::make()->collection('articles')->slug('in-range')
        ->date('2024-06-15')->data(['title' => 'In Range'])->save();
    Entry::make()->collection('notes')->slug('note-in-range')
        ->date('2024-06-20')->data(['content' => []])->save();
    Entry::make()->collection('articles')->slug('out-of-range')
        ->date('2024-07-01')->data(['title' => 'Out'])->save();

    $entries = $this->repo->betweenDates(
        Carbon::parse('2024-06-30'),
        Carbon::parse('2024-06-01'),
    );

    $slugs = $entries->map(fn (ContentEntry $e) => $e->slug())->all();

    expect($slugs)->toContain('in-range');
    expect($slugs)->toContain('note-in-range');
    expect($slugs)->not->toContain('out-of-range');
});

it('betweenDates() includes entries on the boundary dates', function () {
    Entry::make()->collection('articles')->slug('on-newest')
        ->date('2024-06-30')->data(['title' => 'Newest'])->save();
    Entry::make()->collection('articles')->slug('on-oldest')
        ->date('2024-06-01')->data(['title' => 'Oldest'])->save();

    $entries = $this->repo->betweenDates(
        Carbon::parse('2024-06-30'),
        Carbon::parse('2024-06-01'),
    );

    $slugs = $entries->map(fn (ContentEntry $e) => $e->slug())->all();

    expect($slugs)->toContain('on-newest');
    expect($slugs)->toContain('on-oldest');
});

// ---------------------------------------------------------------------------
// ContentEntry::url()
// ---------------------------------------------------------------------------

it('url() builds /{Y}/{m}/{d}/{slug} for articles', function () {
    Entry::make()->collection('articles')->slug('url-test')
        ->date('2024-08-04')->data(['title' => 'URL test'])->save();

    $entry = $this->repo->findByTypeAndSlug('article', 'url-test');

    expect($entry->url())->toBe('/2024/08/04/url-test');
});

it('url() builds /{Y}/{m}/{d}/{slug} for notes', function () {
    Entry::make()->collection('notes')->slug('note-url')
        ->date('2024-09-15')->data(['content' => []])->save();

    $entry = $this->repo->findByTypeAndSlug('note', 'note-url');

    expect($entry->url())->toBe('/2024/09/15/note-url');
});

// ---------------------------------------------------------------------------
// ContentEntry::tags()
// ---------------------------------------------------------------------------

it('tags() returns tags array for articles', function () {
    Entry::make()->collection('articles')->slug('tagged')
        ->date('2024-05-01')->data(['title' => 'Tagged', 'tags' => ['PHP', 'Laravel']])->save();

    $entry = $this->repo->findByTypeAndSlug('article', 'tagged');

    expect($entry->tags())->toBe(['PHP', 'Laravel']);
});

it('tags() returns empty array for notes', function () {
    Entry::make()->collection('notes')->slug('note-notags')
        ->date('2024-05-01')->data(['content' => []])->save();

    $entry = $this->repo->findByTypeAndSlug('note', 'note-notags');

    expect($entry->tags())->toBe([]);
});
