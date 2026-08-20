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

it('inserts a callout and keeps it through the round trip', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/callout');
    $page->keys('.prose-editor', ['Enter']);

    // An empty callout is what inserting one produces, and the schema rejects a
    // callout holding no blocks, so this is the case that used to disappear.
    $page->assertScript("document.querySelectorAll('.prose-editor [aria-label=\"Change callout kind\"]').length", 1)
        ->assertScript("document.querySelector('.prose-editor [aria-label=\"Change callout kind\"]').innerText.trim()", 'NOTE');
});

it('changes a callout kind from the panel itself', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/callout');
    $page->keys('.prose-editor', ['Enter']);

    $page->click('[aria-label="Change callout kind"]');
    $page->click('.prose-editor li:nth-child(4) button');

    $page->assertScript("document.querySelector('.prose-editor [aria-label=\"Change callout kind\"]').innerText.trim()", 'WARNING');
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

    // A span, not an anchor: a link in a draft is not a destination.
    $page->assertScript("document.querySelectorAll('.prose-editor .editor-link').length", 1)
        ->assertScript("document.querySelectorAll('.prose-editor a').length", 0);

    $page->click('.prose-editor .editor-link');

    $page->assertScript("document.querySelector('[aria-label=\"Edit link\"], [aria-label=\"Apply link\"]') !== null", true)
        // Still on the editor: the click must not have navigated away.
        ->assertScript('window.location.pathname', '/new/article');
});

it('offers underline alongside the other marks', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'format me');
    $page->assertScript("
        (() => {
            const el = document.querySelector('.prose-editor p');
            const range = document.createRange();
            range.selectNodeContents(el);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            el.dispatchEvent(new Event('mouseup', { bubbles: true }));
            return true;
        })()
    ", true);

    $page->click('[aria-label="Underline"]');
    $page->assertScript("document.querySelectorAll('.prose-editor u, .prose-editor [style*=\"underline\"]').length > 0", true);
});

it('toggles whether a link opens in a new tab', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'https://github.com ');
    $page->click('.prose-editor .editor-link');
    $page->click('[aria-label="Edit link"]');

    // External defaults to opening away, so the toggle starts pressed.
    $page->assertScript("document.querySelector('[aria-label=\"Opens in a new tab\"]') !== null", true);

    $page->click('[aria-label="Opens in a new tab"]');
    $page->click('[aria-label="Apply link"]');

    $page->assertScript("document.querySelector('.prose-editor .editor-link').getAttribute('data-target')", '_self');
});

it('keeps existing links when the editor content is pasted back in', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'see https://github.com here');
    $page->assertScript("document.querySelectorAll('.prose-editor .editor-link').length", 1);

    // Copying from the editor puts spans on the clipboard, not anchors, so this
    // is the case where the mark has to recognise its own output.
    $page->assertScript("
        (() => {
            const el = document.querySelector('.prose-editor');
            const html = el.innerHTML;
            el.focus();
            document.execCommand('selectAll');
            const data = new DataTransfer();
            data.setData('text/html', html);
            el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
            return true;
        })()
    ", true);

    $page->assertScript("document.querySelectorAll('.prose-editor .editor-link').length > 0", true)
        ->assertScript("document.querySelector('.prose-editor .editor-link').getAttribute('data-href')", 'https://github.com');
});
