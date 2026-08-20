<?php

use App\Models\Article;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows the editor in place of the entry, not above it', function () {
    $article = Article::factory()->create(['title' => 'How OG images work', 'published' => true]);

    $browser = visit($article->url().'?edit');

    // The entry header repeats the title and date the editor already draws,
    // so editing replaces the entry rather than sitting underneath it.
    $browser->assertScript("document.querySelectorAll('h1').length", 1)
        ->assertScript("document.querySelector('#title')?.value", 'How OG images work')
        ->assertScript("document.querySelector('.prose-editor') !== null", true);
});

it('still shows the entry when not editing', function () {
    $article = Article::factory()->create(['title' => 'How OG images work', 'published' => true]);

    visit($article->url())
        ->assertSee('How OG images work')
        ->assertScript("document.querySelector('.prose-editor') === null", true);
});
