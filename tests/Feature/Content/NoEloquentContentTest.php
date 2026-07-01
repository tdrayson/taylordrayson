<?php

use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\get;

// ---------------------------------------------------------------------------
// Class existence: Article, Note, Page Eloquent models must be gone
// ---------------------------------------------------------------------------

it('App\Models\Article class does not exist', function () {
    expect(class_exists('App\Models\Article'))->toBeFalse();
});

it('App\Models\Note class does not exist', function () {
    expect(class_exists('App\Models\Note'))->toBeFalse();
});

it('App\Models\Page class does not exist', function () {
    expect(class_exists('App\Models\Page'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Table existence: articles, notes, pages tables must be dropped
// ---------------------------------------------------------------------------

it('the articles table does not exist', function () {
    expect(Schema::hasTable('articles'))->toBeFalse();
});

it('the notes table does not exist', function () {
    expect(Schema::hasTable('notes'))->toBeFalse();
});

it('the pages table does not exist', function () {
    expect(Schema::hasTable('pages'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Regression: key routes still serve content from Statamic
// ---------------------------------------------------------------------------

it('GET /articles returns 200 (Statamic content still served)', function () {
    get('/articles')->assertOk()->assertInertia(fn ($page) => $page->component('Archive'));
});

it('GET / (timeline) returns 200', function () {
    get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Timeline'));
});

it('GET /feed/rss returns 200', function () {
    get('/feed/rss')->assertOk()->assertSee('<rss', false);
});
