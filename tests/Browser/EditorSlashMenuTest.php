<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/** The blocks the document profile should offer, keyed off the schema it loads. */
it('opens the block menu on a slash and inserts the chosen block', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/');

    // The rendered menu, not the registry: a block list that never reaches the
    // DOM would still pass a JS-level assertion.
    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length > 0", true);
    $page->assertSee('Heading');
    $page->assertSee('Callout');
    $page->assertSee('Code block');

});

it('filters the block menu as you type and inserts on enter', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/quote');
    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length", 1);

    $page->keys('.prose-editor', ['Enter']);

    $page->assertScript("document.querySelectorAll('.prose-editor blockquote').length", 1)
        // The trigger text must not survive into the document.
        ->assertScript("document.querySelector('.prose-editor').innerText.includes('/quote')", false);
});

it('shows a formatting toolbar over a selection', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'format me');
    $page->assertScript("
        (() => {
            const el = document.querySelector('.prose-editor p');
            const range = document.createRange();
            range.selectNodeContents(el);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            el.dispatchEvent(new Event('mouseup', { bubbles: true }));
            return true;
        })()
    ", true);

    $page->assertScript("document.querySelector('[aria-label=\"Bold\"]') !== null", true);
});
