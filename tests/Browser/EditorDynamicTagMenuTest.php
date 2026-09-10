<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('opens the dynamic tag menu on a brace and narrows it as you type', function () {
    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{');

    // The unfiltered menu is cascading, not a flat list of all 36 inline tags:
    // an empty query shows only the first category's tags, Entries' three.
    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length", 3);

    $page->typeSlowly('.prose-editor', 'home');

    // "home" only matches site.home's label, "Home".
    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length", 1)
        ->assertSee('Home');
});

it('dismisses the menu on escape without leaving the typed brace behind', function () {
    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{home');
    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length", 1);

    $page->keys('.prose-editor', ['Escape']);

    $page->assertScript("document.querySelectorAll('[role=\"option\"]').length", 0)
        ->assertScript("document.querySelector('.prose-editor').innerText.trim()", '');
});

it('inserts a chip showing a live value for a tag with no options', function () {
    // A distinctive value, not the config default, so the assertion only
    // passes if the chip actually reads it rather than showing the tag name.
    config(['site.home' => 'Kettlewell, North Yorkshire']);

    $page = visit('/new/note');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '{home');
    $page->keys('.prose-editor', ['Enter']);

    $page->assertScript(
        "document.querySelector('.prose-editor [aria-label=\"Dynamic tag: site.home\"]').innerText.trim()",
        'Kettlewell, North Yorkshire',
    );
});
