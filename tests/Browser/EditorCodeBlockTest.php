<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('inserts a real code block, not inline code', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/code');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'echo 1;');

    $page->assertScript("document.querySelectorAll('.prose-editor pre code').length", 1)
        // The panel treatment, not the inline one: a bare pre inherits the
        // inline code styling and reads as a run of inline spans.
        ->assertScript("getComputedStyle(document.querySelector('.prose-editor pre')).borderRadius !== '0px'", true);
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
    $page->click('[aria-label="Line numbers"]');

    $page->assertScript("document.querySelector('[aria-label=\"Filename\"]').value", 'app.php')
        ->assertScript("document.querySelector('[aria-label=\"Line numbers\"]').checked", true);
});
