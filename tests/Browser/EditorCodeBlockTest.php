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

it('sets the language, filename and line numbers from the block panel', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'echo 1;');

    // The panel appears because the caret is inside a configurable block, not
    // because anything is selected.
    $page->assertScript("document.querySelector('[aria-label=\"Filename\"]') !== null", true);

    $page->type('[aria-label="Filename"]', 'app.php');

    // On by default, so the control is for turning them off.
    $page->assertScript("document.querySelector('[aria-label=\"Line numbers\"]').checked", true);

    $page->click('[aria-label="Line numbers"]');

    $page->assertScript("document.querySelector('[aria-label=\"Filename\"]').value", 'app.php')
        ->assertScript("document.querySelector('[aria-label=\"Line numbers\"]').checked", false);
});

it('highlights the code once a language is chosen', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'const x = 1;');

    $page->select('[aria-label="Language"]', 'javascript');

    // Real highlight.js token classes, not merely any span: the node view has
    // spans of its own, so a loose assertion would pass without highlighting.
    $page->assertScript("document.querySelectorAll('.prose-editor pre code [class^=\"hljs-\"]').length > 0", true);
});

it('shows the filename and a line-number gutter in the editor', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'one');

    $page->type('[aria-label="Filename"]', 'app.js');

    $page->assertSee('app.js')
        ->assertScript("document.querySelectorAll('.prose-editor .code-gutter > span').length", 1);
});

it('hides the block panel when you click outside the editor', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);

    $page->assertScript("document.querySelector('[aria-label=\"Filename\"]') !== null", true);

    // A field further down the form: outside the editor, and far enough from
    // the panel that it is not sitting over the click target.
    $page->click('#slug');

    $page->assertScript("document.querySelector('[aria-label=\"Filename\"]') === null", true);
});

it('colours the highlighted tokens, not just classes them', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'const x = 1;');
    $page->select('[aria-label="Language"]', 'javascript');

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

it('opens the image panel when the image is selected', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/image');
    $page->keys('.prose-editor', ['Enter']);

    // An image is a leaf: it is selected, never entered, so the panel has to
    // read the node selection rather than walk up from the caret.
    $page->assertScript("document.querySelector('[aria-label=\"Alt text\"]') !== null", true)
        ->assertScript("document.querySelector('[aria-label=\"Aspect ratio\"]') !== null", true);
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
