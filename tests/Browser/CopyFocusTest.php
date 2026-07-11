<?php

use App\Models\Article;

// Plain http (non-secure) in tests => navigator.clipboard rejects => the copy
// button uses the legacy execCommand fallback. That fallback must not bounce
// focus off the button (the bug dropped focus to <body> via a removed textarea).
it('keeps focus on the code block copy button after copying', function () {
    $article = Article::factory()->create([
        'title' => 'Copy Focus',
        'occurred_at' => '2019-05-15 12:00:00',
        'content' => [
            ['_type' => 'code', 'code' => "echo 'hello';", 'language' => 'php'],
        ],
    ]);

    $url = '/'.$article->occurred_at->format('Y/m/d').'/'.$article->slug();
    $page = visit($url)->resize(1280, 900);

    // Real click fires the copy; assertScript polls until the async copy settles.
    // Focus must remain on the copy button (its label flips to "Copied").
    $page->click('[aria-label="Copy code"]')
        ->assertScript("document.activeElement?.getAttribute('aria-label')", 'Copied');
});
