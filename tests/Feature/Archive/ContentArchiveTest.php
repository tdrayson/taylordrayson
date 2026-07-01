<?php

use Statamic\Facades\Entry;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

// ---------------------------------------------------------------------------
// /articles index
// ---------------------------------------------------------------------------

it('/articles lists a published Statamic article', function () {
    Entry::make()->collection('articles')->slug('my-first-article')
        ->date('2024-03-10')->data(['title' => 'My First Article', 'excerpt' => 'A great read'])->save();

    get('/articles')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('type', 'article')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'My First Article'))
    );
});

it('/articles excludes draft articles', function () {
    Entry::make()->collection('articles')->slug('live-article')
        ->date('2024-03-10')->data(['title' => 'Live Article'])->save();
    Entry::make()->collection('articles')->slug('draft-article')
        ->date('2024-03-11')->data(['title' => 'Draft Article'])->published(false)->save();

    get('/articles')->assertOk()->assertInertia(fn ($page) => $page
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Live Article')
            && ! archiveTitlesContains($groups, 'Draft Article'))
    );
});

it('/articles paginates Statamic content with 25 per page', function () {
    foreach (range(1, 26) as $i) {
        Entry::make()->collection('articles')->slug("article-{$i}")
            ->date(now()->subDays($i)->toDateString())
            ->data(['title' => "Article {$i}"])->save();
    }

    get('/articles')->assertOk()->assertInertia(fn ($page) => $page
        ->where('currentPage', 1)
        ->where('lastPage', 2)
    );

    get('/articles?page=2')->assertOk()->assertInertia(fn ($page) => $page
        ->where('currentPage', 2)
    );
});

// ---------------------------------------------------------------------------
// /notes index
// ---------------------------------------------------------------------------

it('/notes lists a published Statamic note', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'This is a note']]],
    ];

    Entry::make()->collection('notes')->slug('my-first-note')
        ->date('2024-04-05')->data(['content' => $bardContent])->save();

    get('/notes')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('type', 'note')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'This is a note'))
    );
});

it('/notes excludes draft notes', function () {
    $bardContent = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Published']]]];
    $draftContent = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Draft note']]]];

    Entry::make()->collection('notes')->slug('live-note')
        ->date('2024-04-05')->data(['content' => $bardContent])->save();
    Entry::make()->collection('notes')->slug('draft-note')
        ->date('2024-04-06')->data(['content' => $draftContent])->published(false)->save();

    get('/notes')->assertOk()->assertInertia(fn ($page) => $page
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Published')
            && ! archiveTitlesContains($groups, 'Draft note'))
    );
});

// ---------------------------------------------------------------------------
// /articles/{tag} taxonomy
// ---------------------------------------------------------------------------

it('/articles/{tag} filters to articles with that tag', function () {
    Entry::make()->collection('articles')->slug('tagged-unique-tag')
        ->date('2024-05-01')->data(['title' => 'Unique Tag Article', 'tags' => ['UniqueTag2024']])->save();
    Entry::make()->collection('articles')->slug('tagged-vue')
        ->date('2024-05-02')->data(['title' => 'Vue Article', 'tags' => ['Vue2024']])->save();

    get('/articles/uniquetag2024')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Archive')
        ->where('title', 'Articles tagged UniqueTag2024')
        ->where('subtitle', '1 article')
        ->where('groups', fn ($groups) => archiveTitlesContains($groups, 'Unique Tag Article')
            && ! archiveTitlesContains($groups, 'Vue Article'))
    );
});

it('/articles/{tag} 404s for an unknown tag', function () {
    // The committed article has PHP/Laravel tags; this slug cannot match anything.
    get('/articles/this-tag-will-never-exist-xyzzy')->assertNotFound();
});

it('/articles/{tag} sets the parent breadcrumb to /articles', function () {
    // The committed article already has the 'PHP' tag so /articles/php is a valid route.
    get('/articles/php')->assertOk()->assertInertia(fn ($page) => $page
        ->where('parent.href', '/articles')
        ->where('parent.label', 'Articles')
    );
});

it('/articles exposes tag chips for filtering', function () {
    // The committed article has 'Laravel' tag so chips must include that href without seeding.
    get('/articles')->assertOk()->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->pluck('href')->contains('/articles/laravel'))
    );
});

it('/articles/{tag} marks the active chip', function () {
    // The committed article has PHP and Laravel tags; use those for chip assertions.
    get('/articles/php')->assertOk()->assertInertia(fn ($page) => $page
        ->where('chips', fn ($chips) => collect($chips)->firstWhere('href', '/articles/php')['active'] === true
            && collect($chips)->firstWhere('href', '/articles/laravel')['active'] === false)
    );
});
