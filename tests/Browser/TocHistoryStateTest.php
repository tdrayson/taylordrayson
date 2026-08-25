<?php

use App\Models\Article;
use App\Support\PortableText;

// Inertia keeps the serialised page in history.state. TableOfContents writes
// the hash with replaceState, and passing null there wiped it, so pressing
// Back onto the entry later rewrote the URL without navigating (#287).
it('keeps inertia history state when a contents link is followed', function () {
    $paragraph = fn () => PortableText::block(str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 6));

    $article = Article::factory()->create([
        'published' => true,
        'occurred_at' => '2024-03-01 09:00:00',
        'content' => [
            PortableText::block('First Section', 'h2'),
            ...array_map($paragraph, range(1, 8)),
            PortableText::block('Second Section', 'h3'),
            ...array_map($paragraph, range(1, 8)),
        ],
    ]);

    $page = visit($article->url())->resize(390, 844);

    $page->assertScript("document.querySelectorAll('h2, h3').length >= 2", true);
    $page->assertScript('window.history.state !== null', true);

    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.3);
    $page->click('Contents');

    // Scoped to the sheet: the same text is also the heading in the article.
    $page->script('document.querySelector(\'[role="dialog"] a[href^="#"]\').click()');
    $page->wait(0.3);

    // The hash landed, and the page object survived it.
    $page->assertScript("window.location.hash !== ''", true)
        ->assertScript('window.history.state !== null', true);
});
