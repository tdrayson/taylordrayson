<?php

// Smoke tests for the Statamic migration surface.
// Hits the six site surfaces introduced or re-plumbed by the migration:
// timeline, archives, real committed article entry, search, and CP redirect.
// No seeding — the real committed article entry from content/collections is used.

use function Pest\Laravel\get;

// --- Timeline -----------------------------------------------------------------

it('serves the timeline at /', function () {
    get('/')->assertOk();
});

// --- Archives -----------------------------------------------------------------

it('serves the articles archive at /articles', function () {
    get('/articles')->assertOk();
});

it('serves the notes archive at /notes', function () {
    get('/notes')->assertOk();
});

// --- Real committed article entry ---------------------------------------------

// File: content/collections/articles/2026-05-21-0425.autem-exercitationem-reiciendis-sapiente-voluptatem-porro-iure-atque-repellendus-labore.md
// Title: On building a personal lifelog
it('serves the committed real article entry as an Inertia Entry page', function () {
    get('/2026/05/21/autem-exercitationem-reiciendis-sapiente-voluptatem-porro-iure-atque-repellendus-labore')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entry')
            ->where('type', 'article')
        );
});

// --- Search ------------------------------------------------------------------

it('serves the search page', function () {
    get('/search?q=lifelog')->assertOk();
});

// --- CP redirect -------------------------------------------------------------

it('redirects /cp to the CP login page', function () {
    get('/cp')->assertRedirect('/cp/auth/login');
});
