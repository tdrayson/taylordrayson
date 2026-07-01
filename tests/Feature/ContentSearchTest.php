<?php

use App\Models\Article;
use App\Models\Note;
use Statamic\Facades\Entry;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

/** Build the advanced-search URL from a filter array (mirrors AdvancedSearchTest). */
function contentSearchUrl(array $filter): string
{
    return '/search?'.http_build_query(['filter' => json_encode($filter)]);
}

// ---------------------------------------------------------------------------
// suggest() — the /search/suggest command-palette endpoint
// ---------------------------------------------------------------------------

it('suggest returns a statamic article matched by title', function () {
    Entry::make()->collection('articles')->slug('quokka-post')
        ->date('2024-03-15')->data(['title' => 'The Quokka Chronicles', 'excerpt' => 'A tale'])->save();

    get('/search/suggest?q=quokka')
        ->assertOk()
        ->assertJsonFragment(['title' => 'The Quokka Chronicles'])
        ->assertJsonFragment(['type' => 'article'])
        ->assertJsonFragment(['url' => '/2024/03/15/quokka-post']);
});

it('suggest matches a statamic article by excerpt', function () {
    Entry::make()->collection('articles')->slug('excerpt-match')
        ->date('2024-03-15')->data(['title' => 'Plain Title', 'excerpt' => 'A wombat appeared'])->save();

    get('/search/suggest?q=wombat')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Plain Title']);
});

it('suggest matches a statamic article by body text', function () {
    $body = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A rare platypus sighting today']]],
    ];

    Entry::make()->collection('articles')->slug('body-match')
        ->date('2024-03-15')->data(['title' => 'Nature Log', 'content' => $body])->save();

    get('/search/suggest?q=platypus')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Nature Log']);
});

it('suggest matches a statamic article by tag', function () {
    Entry::make()->collection('articles')->slug('tag-match')
        ->date('2024-03-15')->data(['title' => 'Tagged Piece', 'tags' => ['numbat']])->save();

    get('/search/suggest?q=numbat')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Tagged Piece']);
});

it('suggest matches a statamic note by body text', function () {
    $body = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A quiet bilby moment']]],
    ];

    Entry::make()->collection('notes')->slug('note-body-match')
        ->date('2024-04-10')->data(['content' => $body])->save();

    get('/search/suggest?q=bilby')
        ->assertOk()
        ->assertJsonFragment(['type' => 'note'])
        ->assertJsonFragment(['url' => '/2024/04/10/note-body-match']);
});

it('suggest does not return the eloquent article duplicate', function () {
    // A stale Eloquent Article row: search must NOT surface it.
    Article::factory()->create([
        'title' => 'Stale Eloquent Kookaburra',
        'occurred_at' => now()->subDay(),
    ]);

    get('/search/suggest?q=kookaburra')
        ->assertOk()
        ->assertJsonMissing(['title' => 'Stale Eloquent Kookaburra']);
});

it('suggest does not return the eloquent note duplicate', function () {
    Note::factory()->create([
        'content' => 'Stale Eloquent Dingo note',
        'occurred_at' => now()->subDay(),
    ]);

    get('/search/suggest?q=dingo')
        ->assertOk()
        ->assertJsonMissing(['type' => 'note']);
});

it('suggest returns a migrated post once (statamic, not eloquent)', function () {
    // Same conceptual post exists both as a stale Eloquent row and a live Statamic entry.
    Article::factory()->create(['title' => 'Migrated Echidna', 'occurred_at' => now()->subDay()]);
    Entry::make()->collection('articles')->slug('migrated-echidna')
        ->date('2024-03-15')->data(['title' => 'Migrated Echidna'])->save();

    $response = get('/search/suggest?q=echidna')->assertOk();

    $matches = collect($response->json('results'))
        ->where('title', 'Migrated Echidna');

    expect($matches)->toHaveCount(1);
    expect($matches->first()['url'])->toBe('/2024/03/15/migrated-echidna');
});

it('suggest excludes draft statamic articles', function () {
    Entry::make()->collection('articles')->slug('draft-quoll')
        ->date('2024-03-15')->data(['title' => 'Draft Quoll'])->published(false)->save();

    get('/search/suggest?q=quoll')
        ->assertOk()
        ->assertJsonMissing(['title' => 'Draft Quoll']);
});

it('suggest excludes a non-matching statamic article', function () {
    Entry::make()->collection('articles')->slug('no-match')
        ->date('2024-03-15')->data(['title' => 'Something Unrelated'])->save();

    get('/search/suggest?q=possum')
        ->assertOk()
        ->assertJsonMissing(['title' => 'Something Unrelated']);
});

// ---------------------------------------------------------------------------
// index() — the advanced query builder page
// ---------------------------------------------------------------------------

it('advanced search returns a statamic article by text (Anything group)', function () {
    Entry::make()->collection('articles')->slug('advanced-koala')
        ->date('2024-03-15')->data(['title' => 'A Koala Story', 'excerpt' => 'Eucalyptus'])->save();

    $url = contentSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'text', 'operator' => 'contains', 'value' => 'koala']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('advanced search returns a statamic article by its article title field', function () {
    Entry::make()->collection('articles')->slug('typed-wallaby')
        ->date('2024-03-15')->data(['title' => 'The Wallaby Report'])->save();

    $url = contentSearchUrl([[
        'type' => 'article',
        'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'wallaby']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('advanced search matches an article by tag', function () {
    Entry::make()->collection('articles')->slug('tagged-emu')
        ->date('2024-03-15')->data(['title' => 'Bird Watch', 'tags' => ['emu']])->save();

    $url = contentSearchUrl([[
        'type' => 'article',
        'conditions' => [['field' => 'content', 'operator' => 'contains', 'value' => 'emu']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('advanced search filters content by a date range', function () {
    Entry::make()->collection('articles')->slug('in-range')
        ->date('2024-03-15')->data(['title' => 'In Range Cassowary'])->save();
    Entry::make()->collection('articles')->slug('out-range')
        ->date('2024-06-15')->data(['title' => 'Out Range Cassowary'])->save();

    $url = contentSearchUrl([[
        'type' => 'article',
        'conditions' => [
            ['field' => 'title', 'operator' => 'contains', 'value' => 'cassowary'],
            ['field' => 'day', 'operator' => 'between', 'value' => ['2024-03-01', '2024-03-31']],
        ],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});

it('advanced search does not return the eloquent article duplicate', function () {
    Article::factory()->create(['title' => 'Stale Advanced Galah', 'occurred_at' => now()->subDay()]);

    $url = contentSearchUrl([[
        'type' => 'article',
        'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'galah']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 0));
});

it('advanced search excludes draft statamic articles', function () {
    Entry::make()->collection('articles')->slug('draft-magpie')
        ->date('2024-03-15')->data(['title' => 'Draft Magpie'])->published(false)->save();

    $url = contentSearchUrl([[
        'type' => 'article',
        'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'magpie']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 0));
});

it('advanced search still returns non-content types alongside content', function () {
    Entry::make()->collection('articles')->slug('mixed-numbat')
        ->date('2024-03-15')->data(['title' => 'Mixed Numbat'])->save();

    $url = contentSearchUrl([[
        'type' => 'any',
        'conditions' => [['field' => 'text', 'operator' => 'contains', 'value' => 'numbat']],
    ]]);

    get($url)->assertOk()->assertInertia(fn ($page) => $page->where('total', 1));
});
