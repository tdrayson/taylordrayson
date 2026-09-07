<?php

use App\Models\Article;
use App\Models\Note;
use App\Support\PortableText;

/**
 * What a microformats consumer reads back as our content.
 *
 * `e-content` is published as HTML, so anything inside that element travels to
 * whoever parses us. Vue's own anchors are unavoidable on the page at large,
 * but nothing that is merely behaviour belongs inside the content itself.
 */
$noComments = "(() => {
    for (const el of document.querySelectorAll('.e-content')) {
        const walker = document.createTreeWalker(el, NodeFilter.SHOW_COMMENT);
        if (walker.nextNode()) { return false; }
    }
    return true;
})()";

it('publishes a note body with nothing in it but the note', function () use ($noComments) {
    Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'content' => PortableText::fromPlainText('A note with several words and no link at all.'),
    ]);

    // The hover preview layer used to render inside this element, so its
    // teleport anchors were part of the content a consumer read back.
    visit('/')->assertPresent('.e-content')->assertScript($noComments, true);
});

it('publishes an article body with nothing in it but the article', function () use ($noComments) {
    $article = Article::factory()->create([
        'published' => true,
        'title' => 'A piece',
        'occurred_at' => now()->subDay(),
        'content' => PortableText::fromPlainText('The body of the piece.'),
    ]);

    // Same for the lightbox, which is a viewer rather than anything written.
    visit($article->url())->assertPresent('.e-content')->assertScript($noComments, true);
});
