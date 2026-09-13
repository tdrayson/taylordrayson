<?php

use App\Models\Citation;
use App\Models\Note;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('previews the stored copy and saves a trimmed quote', function () {
    // Stored up front, so the endpoint answers without fetching a real page.
    Citation::factory()->create([
        'url' => 'https://example.com/post',
        'title' => 'Sending your First Webmention',
        'author_name' => 'Aaron Parecki',
        'excerpt' => 'The whole opening paragraph.',
    ]);

    $page = visit('/new/note');
    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'Agreed.', 20);
    $page->select('#response_kind', 'reply');
    $page->fill('#response_url', 'https://example.com/post');
    $page->click('#slug');

    $page->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('[data-testid=citation-preview] .p-author')?.textContent.trim()), 1500))", 'Aaron Parecki');

    // Shown once, in the preview, until asked to edit it there.
    $page->assertMissing('#response_quote');
    $page->click('button:has-text("Edit quote")');
    $page->assertScript("document.querySelector('[data-testid=citation-preview] #response_quote').value", 'The whole opening paragraph.');

    $page->fill('#response_quote', 'Just this bit.');
    $page->click('button:has-text("Done")');
    $page->assertScript("document.querySelector('[data-testid=citation-preview] .p-content')?.textContent.trim()", 'Just this bit.');

    $page->fill('#slug', 'agreed');
    $page->click('button:has-text("Post")');
    $page->assertScript("location.pathname !== '/new/note'", true);

    expect(Note::sole()->response_quote)->toBe('Just this bit.');
});

it('drops a trimmed quote when the reply url is changed to a different post', function () {
    Citation::factory()->create([
        'url' => 'https://example.com/post-a',
        'title' => 'Post A',
        'author_name' => 'Author A',
        'excerpt' => 'Excerpt A.',
    ]);
    Citation::factory()->create([
        'url' => 'https://example.com/post-b',
        'title' => 'Post B',
        'author_name' => 'Author B',
        'excerpt' => 'Excerpt B.',
    ]);

    $page = visit('/new/note');
    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'Agreed.', 20);
    $page->select('#response_kind', 'reply');
    $page->fill('#response_url', 'https://example.com/post-a');
    $page->click('#slug');

    $page->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('[data-testid=citation-preview] .p-content')?.textContent.trim()), 1500))", 'Excerpt A.');

    $page->click('button:has-text("Edit quote")');
    $page->fill('#response_quote', 'Only part of A.');

    $page->fill('#response_url', 'https://example.com/post-b');
    $page->click('#slug');

    $page->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('[data-testid=citation-preview] .p-content')?.textContent.trim()), 1500))", 'Excerpt B.');
    $page->assertMissing('#response_quote');
});

it('stores an untouched excerpt as an empty quote', function () {
    Citation::factory()->create(['url' => 'https://example.com/post', 'excerpt' => 'The whole opening paragraph.']);
    $note = Note::factory()->create(['response_kind' => 'reply', 'response_url' => 'https://example.com/post']);

    $page = visit($note->url().'?edit');

    $page->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('[data-testid=citation-preview] .p-content')?.textContent.trim()), 1500))", 'The whole opening paragraph.');
    $page->click('button:has-text("Edit quote")');
    $page->click('button:has-text("Done")');

    // Left as the excerpt, it stays empty: nothing to offer "Reset to excerpt" for.
    $page->assertMissing('button:has-text("Reset to excerpt")');
});

it('says nothing while the url is not a link yet', function () {
    $page = visit('/new/note');
    $page->select('#response_kind', 'reply');
    $page->fill('#response_url', 'not a link');
    $page->click('#slug');

    $page->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('[data-testid=citation-preview]').textContent.trim()), 1000))", '');
});
