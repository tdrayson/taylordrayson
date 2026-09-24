<?php

use App\Models\Article;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('opens an entry with its tags as named chips', function () {
    $article = Article::factory()->create([
        'title' => 'A tagged piece',
        'status' => 'published',
        'occurred_at' => now()->subDay(),
    ]);
    $article->syncTagNames(['Living Alone', 'Fitness']);

    // The entry payload carries {name, slug} for the footer's links, so the
    // form seeing that shape would print the raw object in each chip.
    visit($article->fresh()->url().'?edit')->assertScript(
        "(() => { const label = [...document.querySelectorAll('label,span,div')].find(el => el.textContent.trim() === 'Tags'); return label?.parentElement?.innerText.replace(/\\n+/g, ' ') ?? 'none'; })()",
        'TAGS Living Alone × Fitness ×',
    );
});

it('closes the tag suggestions when focus moves to another field', function () {
    Article::factory()->create(['status' => 'published', 'occurred_at' => now()->subDays(2)])
        ->syncTagNames(['Fitness']);
    $article = Article::factory()->create(['status' => 'published', 'occurred_at' => now()->subDay()]);

    $tags = 'input[placeholder="Add a tag"]';
    $expanded = "document.querySelector('{$tags}').getAttribute('aria-expanded')";

    $page = visit($article->url().'?edit')->assertPresent($tags);

    $page->click($tags)->assertScript($expanded, 'true');

    $page->keys($tags, 'Shift+Tab')->assertScript($expanded, 'false');
});
