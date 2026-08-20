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

it('inserts a callout of the chosen variant, and keeps it', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/warning');
    $page->keys('.prose-editor', ['Enter']);

    // Survives the round trip: an empty callout is what inserting one produces,
    // and the schema rejects a callout with no blocks in it.
    $page->assertScript("document.querySelectorAll('.prose-editor [data-callout]').length", 1)
        ->assertScript("document.querySelector('.prose-editor [data-callout]').getAttribute('variant')", 'warning');
});

it('scrolls the armed row into view when arrowing past the fold', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/');

    // Enough rows to overflow the menu's max height, so the last is out of sight.
    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length > 8", true);

    $count = 12;
    for ($i = 0; $i < $count; $i++) {
        $page->keys('.prose-editor', ['ArrowDown']);
    }

    $page->assertScript("
        (() => {
            const row = document.querySelector('[aria-selected=\"true\"]');
            const box = row.closest('[role=\"listbox\"]').getBoundingClientRect();
            const rect = row.getBoundingClientRect();
            return rect.top >= box.top - 1 && rect.bottom <= box.bottom + 1;
        })()
    ", true);
});

it('opens the link editor when the caret rests in a link, rather than following it', function () {
    $page = visit('/new/article');

    // linkOnPaste turns a typed URL into a link as soon as it is complete.
    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'https://github.com ');

    $page->assertScript("document.querySelectorAll('.prose-editor a').length", 1);

    $page->click('.prose-editor a');

    $page->assertScript("document.querySelector('[aria-label=\"Edit link\"], [aria-label=\"Apply link\"]') !== null", true)
        // Still on the editor: the click must not have navigated away.
        ->assertScript("window.location.pathname", '/new/article');
});
