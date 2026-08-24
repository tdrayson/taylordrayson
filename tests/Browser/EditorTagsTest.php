<?php

use App\Models\Article;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('opens an entry with its tags as named chips', function () {
    $article = Article::factory()->create([
        'title' => 'A tagged piece',
        'published' => true,
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
