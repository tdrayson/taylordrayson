<?php

use App\Models\Article;
use App\Models\User;

it('switches the writing column between tabs, keeping the title in view', function () {
    $this->actingAs(User::factory()->create());

    visit('/new/article')
        ->assertPresent('button[aria-pressed]:has-text("Content")')
        ->assertPresent('button[aria-pressed]:has-text("Summary")')
        ->assertPresent('button[aria-pressed]:has-text("Response")')
        ->assertPresent('button[aria-pressed]:has-text("Social")')
        ->assertMissing('#excerpt')
        ->click('button[aria-pressed]:has-text("Summary")')
        ->assertVisible('#excerpt')
        ->assertVisible('#title')
        ->assertNoJavascriptErrors();
});

it('duplicates a saved article into a new one without its slug', function () {
    $this->actingAs(User::factory()->create());
    $article = Article::factory()->create(['status' => 'published', 'title' => 'Original piece', 'slug' => 'original-piece', 'occurred_at' => now()->subDay()]);

    $page = visit("{$article->url()}?edit")->resize(1280, 900);

    $page->click('aside button[aria-label="More actions"]')
        ->click('[role="menuitem"]:has-text("Duplicate")')
        ->assertPathIs('/new/article')
        ->assertScript("document.querySelector('#title').value", 'Original piece')
        ->assertScript("document.querySelector('#slug').value", '')
        ->assertNoJavascriptErrors();
});

it('deletes a saved article after confirming', function () {
    $this->actingAs(User::factory()->create());
    $article = Article::factory()->create(['status' => 'published', 'title' => 'Doomed piece', 'occurred_at' => now()->subDay()]);

    $page = visit("{$article->url()}?edit")->resize(1280, 900);

    $page->click('aside button[aria-label="More actions"]')
        ->click('[role="menuitem"]:has-text("Delete")')
        ->click('[role="dialog"] button:has-text("Delete")')
        ->assertPathIs('/articles');

    expect(Article::query()->whereKey($article->id)->exists())->toBeFalse();
});
