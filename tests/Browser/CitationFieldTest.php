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

    $page->fill('#response_quote', 'Just this bit.');
    $page->fill('#slug', 'agreed');
    $page->click('button:has-text("Post")');
    $page->assertScript("location.pathname !== '/new/note'", true);

    expect(Note::sole()->response_quote)->toBe('Just this bit.');
});

it('clears the pre-filled quote when the reply url is changed to a different post', function () {
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

    $page->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('#response_quote')?.value), 1500))", 'Excerpt A.');

    $page->fill('#response_url', 'https://example.com/post-b');

    $page->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('#response_quote')?.value), 1500))", 'Excerpt B.');
});
