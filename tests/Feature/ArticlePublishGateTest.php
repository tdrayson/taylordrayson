<?php

use App\Models\Article;
use App\Models\User;

it('creates no timeline entry for an unpublished article', function () {
    $article = Article::factory()->create(['published' => false]);

    expect($article->timelineEntry)->toBeNull();
});

it('creates the timeline entry when the article is published', function () {
    $article = Article::factory()->create(['published' => false]);
    $article->update(['published' => true]);

    expect($article->fresh()->timelineEntry)->not->toBeNull();
});

it('removes the timeline entry when an article is unpublished', function () {
    $article = Article::factory()->create(['published' => true]);
    $article->update(['published' => false]);

    expect($article->fresh()->timelineEntry)->toBeNull();
});

it('404s an unpublished article entry page for guests but shows it when authenticated', function () {
    $article = Article::factory()->create(['published' => false]);
    $url = $article->url();

    $this->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertSuccessful();
});

it('shows a published article entry page to guests', function () {
    $article = Article::factory()->create(['published' => true]);

    $this->get($article->url())->assertSuccessful();
});
