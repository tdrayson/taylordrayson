<?php

use App\Models\Article;
use App\Models\Note;
use App\Support\PortableText;

// Microformats are a machine contract: an IndieWeb reader or webmention
// receiver parses this markup, and nothing on the page looks wrong when it
// regresses. Asserting the rendered DOM, since the classes only exist there.

it('marks an article permalink up as an h-entry', function () {
    $article = Article::factory()->create([
        'published' => true,
        'title' => 'A titled piece',
        'occurred_at' => '2024-03-01 09:00:00',
        'content' => PortableText::fromPlainText('The body of the piece.'),
    ]);

    visit($article->url())
        ->assertPresent('.h-entry .p-name')
        ->assertPresent('.h-entry .dt-published[datetime]')
        ->assertPresent('.h-entry .u-url')
        ->assertPresent('.h-entry .e-content')
        ->assertPresent('.h-entry .p-author.h-card');
});

// The distinction readers use to tell the two apart: a note is content with no
// name of its own, so the outline-only heading must not carry p-name.
it('leaves a note without a name, so it does not parse as an article', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-02 09:00:00',
        'content' => PortableText::fromPlainText('Just a thought.'),
    ]);

    visit($note->url())
        ->assertPresent('.h-entry .e-content')
        ->assertMissing('.h-entry .p-name');
});

it('wraps the timeline in an authored h-feed', function () {
    Article::factory()->create(['published' => true, 'occurred_at' => now()->subHour()]);

    visit('/')
        ->assertPresent('.h-feed')
        ->assertPresent('.h-feed .p-author.h-card')
        ->assertPresent('.h-feed .h-entry');
});
