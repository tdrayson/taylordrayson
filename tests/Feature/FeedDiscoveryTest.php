<?php

use function Pest\Laravel\get;

it('advertises the site-wide feeds on every page', function () {
    get('/')
        ->assertSuccessful()
        ->assertSee('type="application/rss+xml" title="Taylor Drayson (RSS)" href="/feed/rss">', false)
        ->assertSee('type="application/atom+xml" title="Taylor Drayson (Atom)" href="/feed">', false)
        ->assertSee('type="application/feed+json" title="Taylor Drayson (JSON)" href="/feed/json">', false);
});

it('adds no type-scoped feed on the timeline', function () {
    get('/')->assertDontSee('?types=', false);
});

it('advertises a type-scoped feed alongside the site-wide feeds on archive pages', function (string $slug, string $type) {
    get("/{$slug}")
        ->assertSuccessful()
        ->assertSee('href="/feed/rss?types='.$type.'">', false)
        ->assertSee('href="/feed?types='.$type.'">', false)
        ->assertSee('href="/feed/json?types='.$type.'">', false)
        ->assertSee('href="/feed/rss">', false); // site-wide feed still present
})->with([
    'articles' => ['articles', 'article'],
    'flights' => ['flights', 'flight'],
    'notes' => ['notes', 'note'],
]);
