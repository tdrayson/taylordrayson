<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('starts a new paragraph on enter', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'first');
    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'second');

    $page->assertScript("document.querySelectorAll('.prose-editor p').length", 2)
        ->assertScript("document.querySelectorAll('.prose-editor p')[1].innerText", 'second');
});

it('converts markdown shortcuts as you type', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '## a heading');
    $page->assertScript("document.querySelectorAll('.prose-editor h2').length", 1);

    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', '- a bullet');
    $page->assertScript("document.querySelectorAll('.prose-editor ul li').length", 1);
});

it('offers headings two through four and no others', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/heading');

    $page->assertScript("[...document.querySelectorAll('[role=\"option\"]')].map(el => el.innerText.split('\\n')[0]).join(',')", 'Heading 2,Heading 3,Heading 4');
});

it('shows one placeholder, on the empty paragraph holding the caret', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'first');
    $page->keys('.prose-editor', ['Enter']);
    $page->keys('.prose-editor', ['Enter']);

    $page->assertScript("document.querySelectorAll('.prose-editor .is-empty[data-placeholder]').length", 1);
});

it('does not offer the writing hint inside an empty heading', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', '## ');

    $page->assertScript("document.querySelectorAll('.prose-editor h2').length", 1)
        ->assertScript("document.querySelector('.prose-editor h2').getAttribute('data-placeholder') || ''", '');
});

it('still starts a new paragraph when the caret sits in a link', function () {
    $page = visit('/new/article');

    $page->click('.prose-editor')->typeSlowly('.prose-editor', 'see https://github.com ');
    $page->assertScript("document.querySelectorAll('.prose-editor a').length", 1);

    $page->keys('.prose-editor', ['Enter']);
    $page->typeSlowly('.prose-editor', 'next line');

    $page->assertScript("document.querySelectorAll('.prose-editor p').length", 2);
});
