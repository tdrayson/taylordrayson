<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('inserts a real code block, not inline code', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'echo 1;');

    $page->assertScript("document.querySelectorAll('.prose-editor pre code').length", 1)
        // The panel treatment, not the inline one: the chrome lives on the node
        // view's wrapper, with the pre inside it carrying only the code layout.
        ->assertScript("document.querySelectorAll('.prose-editor .not-prose pre code').length", 1);
});

it('indents with tab inside a code block', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->keys('.prose-editor', ['Tab']);
    $page->typeSlowly('.prose-editor', 'indented');

    $page->assertScript("document.querySelector('.prose-editor pre code').textContent", '    indented');
});

it('leaves tab alone outside a code block, so focus still moves', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'plain');
    $page->keys('.prose-editor', ['Tab']);

    $page->assertScript("document.querySelector('.prose-editor p').textContent", 'plain');
});

it('sets the filename and line numbers from the block settings', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'echo 1;');

    $page->click('[aria-label="Code settings"]');

    $page->fill('[aria-label="Filename"]', 'app.php');

    // On by default, so the control is for turning them off.
    $page->assertScript("document.querySelector('[aria-label=\"Line numbers\"]').getAttribute('aria-checked')", 'true');

    $page->click('[aria-label="Line numbers"]');
    $page->click('button:has-text("Apply")');

    // The block, not the form: the form is gone, and what matters is that the
    // settings reached the block.
    $page->assertSee('app.php')
        ->assertScript("document.querySelectorAll('.prose-editor .code-gutter').length", 0);
});

it('highlights the code once a language is chosen', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'const x = 1;');

    $page->click('[aria-label="Code settings"]');
    $page->select('[aria-label="Language"]', 'javascript');
    $page->click('button:has-text("Apply")');

    // Real highlight.js token classes, not merely any span: the node view has
    // spans of its own, so a loose assertion would pass without highlighting.
    $page->assertScript("document.querySelectorAll('.prose-editor pre code [class^=\"hljs-\"]').length > 0", true);
});

it('shows the filename and a line-number gutter in the editor', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'one');

    $page->click('[aria-label="Code settings"]');
    $page->fill('[aria-label="Filename"]', 'app.js');
    $page->click('button:has-text("Apply")');

    $page->assertSee('app.js')
        ->assertScript("document.querySelectorAll('.prose-editor .code-gutter > span').length", 1);
});

it('closes the block settings on escape, leaving the block alone', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);

    $page->click('[aria-label="Code settings"]');
    $page->fill('[aria-label="Filename"]', 'abandoned.php');

    // Dismissal is useDialog's, shared with every other modal, rather than the
    // panel's own outside-click rule. Nothing typed is kept: the form stages,
    // and only Apply writes.
    $page->keys('[role="dialog"]', 'Escape');

    $page->assertScript("document.querySelector('[aria-label=\"Filename\"]') === null", true)
        ->assertDontSee('abandoned.php');
});

it('colours the highlighted tokens, not just classes them', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'const x = 1;');

    $page->click('[aria-label="Code settings"]');
    $page->select('[aria-label="Language"]', 'javascript');
    $page->click('button:has-text("Apply")');

    // The classes existed all along; what was missing was any rule colouring
    // them, because the theme was scoped inside the published component.
    $page->assertScript("
        (() => {
            const token = document.querySelector('.prose-editor pre code [class^=\"hljs-\"]');
            const code = document.querySelector('.prose-editor pre code');
            return getComputedStyle(token).color !== getComputedStyle(code).color;
        })()
    ", true);
});

it('keeps each code line on one row so the gutter stays in step', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', str_repeat('long_code_line ', 12), 1);

    // One number, one row: wrapping would give the line two rows and one number.
    $page->assertScript("getComputedStyle(document.querySelector('.prose-editor pre code')).whiteSpace", 'pre')
        ->assertScript("document.querySelectorAll('.prose-editor .code-gutter > span').length", 1)
        ->assertScript("document.querySelector('.prose-editor pre').scrollWidth > document.querySelector('.prose-editor pre').clientWidth", true);
});

it('sets an image alt text from the block settings', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/image');
    $page->keys('.prose-editor', ['Enter']);

    // Nothing to configure while the block is still a dropzone: alt text on an
    // image that does not exist yet is meaningless.
    $page->assertScript("document.querySelector('[aria-label=\"Image settings\"]') === null", true);

    $page->fill('[placeholder="or paste an image URL"]', 'https://example.com/a.jpg');
    $page->click('button:has-text("Use")');

    $page->click('[aria-label="Image settings"]');
    $page->fill('[aria-label="Alt text"]', 'A described image');
    $page->click('button:has-text("Apply")');

    // An image is a leaf, selected rather than entered, so applying goes
    // through the node view's own handle on itself and never the selection.
    $page->assertScript("document.querySelector('.prose-editor figure img').getAttribute('alt')", 'A described image');
});

it('lines the gutter numbers up with the code lines', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'one');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'two');

    // Matching metrics are what keep a number on its line, and a marker or list
    // spacing on the gutter throws both out. It was an `ol`, and the editor's
    // list rules outrank the utility classes that were meant to suppress them.
    $page->assertScript("
        (() => {
            const gutter = getComputedStyle(document.querySelector('.prose-editor .code-gutter'));
            const code = getComputedStyle(document.querySelector('.prose-editor pre.code-body'));

            return gutter.fontSize === code.fontSize
                && gutter.lineHeight === code.lineHeight
                && gutter.paddingTop === code.paddingTop;
        })()
    ", true);

    $page->assertScript("
        (() => {
            const rows = [...document.querySelectorAll('.prose-editor .code-gutter > span')];
            const step = rows[1].getBoundingClientRect().top - rows[0].getBoundingClientRect().top;
            const line = parseFloat(getComputedStyle(document.querySelector('.prose-editor pre.code-body')).lineHeight);

            return Math.abs(step - line) < 1;
        })()
    ", true);
});
