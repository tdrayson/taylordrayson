<?php

use App\Models\Article;
use App\Models\Note;
use App\Models\Page;
use App\Models\Podcast;

function assertTwemojiLoaded($page): void
{
    $page->assertScript("document.querySelectorAll('img.emoji').length > 0", true)
        ->assertScript(
            "(() => { const img = document.querySelector('img.emoji'); return !!img && img.src.includes('cdn.jsdelivr.net/gh/jdecked/twemoji@') && img.complete && img.naturalWidth > 0; })()",
            true,
        );
}

it('renders content emoji as a Twemoji image on the timeline', function () {
    Note::factory()->create([
        'content' => 'Great workout today 👍',
        'occurred_at' => now()->subHour(),
    ]);

    assertTwemojiLoaded(visit('/'));
});

it('renders content emoji as a Twemoji image on a note detail page', function () {
    $note = Note::factory()->create([
        'content' => 'Great workout today 👍',
        'occurred_at' => now()->subDay(),
    ]);

    assertTwemojiLoaded(visit($note->fresh()->url()));
});

it('renders emoji in a page excerpt', function () {
    Page::factory()->create([
        'slug' => 'about-emoji',
        'title' => 'About',
        'excerpt' => 'Hello from the excerpt 👍',
        'published' => true,
    ]);

    assertTwemojiLoaded(visit('/about-emoji'));
});

it('renders emoji in an article excerpt', function () {
    $article = Article::factory()->create([
        'title' => 'Emoji excerpt article',
        'excerpt' => 'A short summary with 👍',
        // Keep body free of emoji so a pass must come from the excerpt.
        'content' => [['_type' => 'block', 'children' => [['_type' => 'span', 'text' => 'Plain body.']]]],
        'published' => true,
        'occurred_at' => now()->subDay(),
    ]);

    assertTwemojiLoaded(visit($article->fresh()->url()));
});

it('renders emoji in podcast show notes', function () {
    $podcast = Podcast::factory()->create([
        'topic' => 'Shipping',
        'show_notes' => 'Notes with a thumbs up 👍',
        'occurred_at' => now()->subDay(),
    ]);

    assertTwemojiLoaded(visit($podcast->fresh()->url()));
});
