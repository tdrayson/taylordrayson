<?php

use function Pest\Laravel\get;

it('advertises the site-wide feeds on every page', function () {
    get('/')
        ->assertSuccessful()
        ->assertSee('type="application/rss+xml" title="Taylor Drayson (RSS)" href="/feed/rss">', false)
        ->assertSee('type="application/atom+xml" title="Taylor Drayson (Atom)" href="/feed">', false)
        ->assertSee('type="application/feed+json" title="Taylor Drayson (JSON)" href="/feed/json">', false);
});

it('shares no type-scoped feed on the timeline', function () {
    get('/')->assertSuccessful()->assertInertia(fn ($page) => $page->where('contextualFeeds', []));
});

it('shares a type-scoped feed alongside the site-wide feeds on archive pages', function (string $slug, string $type, string $label) {
    get("/{$slug}")
        ->assertSuccessful()
        ->assertSee('href="/feed/rss">', false) // site-wide feeds still in the document root
        ->assertInertia(fn ($page) => $page->where('contextualFeeds', [
            ['type' => 'application/atom+xml', 'title' => "Taylor Drayson: {$label} (Atom)", 'href' => "/feed?types={$type}"],
            ['type' => 'application/rss+xml', 'title' => "Taylor Drayson: {$label} (RSS)", 'href' => "/feed/rss?types={$type}"],
            ['type' => 'application/feed+json', 'title' => "Taylor Drayson: {$label} (JSON)", 'href' => "/feed/json?types={$type}"],
        ]));
})->with([
    'articles' => ['articles', 'article', 'Articles'],
    'flights' => ['flights', 'flight', 'Flights'],
    'notes' => ['notes', 'note', 'Notes'],
]);

// The contextual links live in AppHead so an Inertia visit rewrites them; the
// document root is rendered once and must carry only the constant ones.
it('leaves only the site-wide feeds in the document root', function () {
    preg_match_all('/<link rel="alternate"[^>]*>/', get('/flights')->assertSuccessful()->getContent(), $matches);

    expect($matches[0])->toHaveCount(3)->each->not->toContain('types=');
});
