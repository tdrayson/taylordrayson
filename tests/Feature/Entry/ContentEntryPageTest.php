<?php

use Statamic\Facades\Entry;

beforeEach(function () {
    $this->preContent = snapshotContentFiles();
});

afterEach(function () {
    deleteNewContentFiles($this->preContent);
});

// ---------------------------------------------------------------------------
// Article rendering
// ---------------------------------------------------------------------------

it('renders a statamic article entry via Inertia', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello from Statamic']]],
    ];

    Entry::make()->collection('articles')->slug('p1')
        ->date('2024-01-02')
        ->data(['title' => 'My Article', 'content' => $bardContent])
        ->save();

    $this->get('/2024/01/02/p1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entry')
            ->where('type', 'article')
            ->where('title', 'My Article')
            ->where('accent', 'article')
            ->where('source', null)
            ->where('entry.bodyHtml', fn ($value) => str_contains($value, 'Hello from Statamic'))
        );
});

// ---------------------------------------------------------------------------
// Note rendering
// ---------------------------------------------------------------------------

it('renders a statamic note entry via Inertia', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello from a note']]],
    ];

    Entry::make()->collection('notes')->slug('n1')
        ->date('2024-01-03')
        ->data(['content' => $bardContent])
        ->save();

    $this->get('/2024/01/03/n1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entry')
            ->where('type', 'note')
            ->where('accent', 'note')
            ->where('source', null)
            ->where('entry.bodyHtml', fn ($value) => str_contains($value, 'Hello from a note'))
        );
});

// ---------------------------------------------------------------------------
// Draft returns 404
// ---------------------------------------------------------------------------

it('returns 404 for a draft article', function () {
    Entry::make()->collection('articles')->slug('secret-draft')
        ->date('2024-01-04')
        ->data(['title' => 'Secret Draft'])
        ->published(false)
        ->save();

    $this->get('/2024/01/04/secret-draft')->assertNotFound();
});

// ---------------------------------------------------------------------------
// Date mismatch falls through (no Eloquent match = 404)
// ---------------------------------------------------------------------------

it('returns 404 when slug exists but date does not match', function () {
    Entry::make()->collection('articles')->slug('date-mismatch')
        ->date('2024-01-05')
        ->data(['title' => 'Mismatch'])
        ->save();

    // Request a different date for the same slug
    $this->get('/2024/02/05/date-mismatch')->assertNotFound();
});

// ---------------------------------------------------------------------------
// Article excerpt is passed through
// ---------------------------------------------------------------------------

it('passes excerpt in the entry payload for statamic articles', function () {
    $bardContent = [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Body text here']]],
    ];

    Entry::make()->collection('articles')->slug('with-excerpt')
        ->date('2024-01-06')
        ->data(['title' => 'Excerpt Article', 'excerpt' => 'Short summary', 'content' => $bardContent])
        ->save();

    $this->get('/2024/01/06/with-excerpt')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entry.excerpt', 'Short summary')
        );
});
