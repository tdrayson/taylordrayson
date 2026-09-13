<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/** Types some text and selects the whole line, ready to link it. */
function selectTypedLine($page, string $text): void
{
    $page->click('.prose-editor')->typeSlowly('.prose-editor', $text);
    $page->keys('.prose-editor', ['Home']);
    $page->keys('.prose-editor', ['Shift+End']);
}

it('offers only href-capable tags as a link target', function () {
    $page = visit('/new/note');

    selectTypedLine($page, 'some text');
    $page->click('[aria-label="Add link"]');

    // entries.photo is image-only and must never appear here.
    $page->assertScript(
        "[...document.querySelectorAll('[aria-label=\"Link target\"] option')].map(o => o.value).includes('entries.photo')",
        false,
    )->assertScript(
        "[...document.querySelectorAll('[aria-label=\"Link target\"] option')].map(o => o.value).includes('site.social')",
        true,
    );
});

it('points a link at a dynamic tag and marks it as one in the rendered document', function () {
    // A distinctive value, not the config default, so this only passes if the
    // link actually resolves against the chosen network.
    config(['site.social' => ['github' => 'https://github.com/a-distinctive-account']]);

    $page = visit('/new/note');

    selectTypedLine($page, 'my github');
    $page->click('[aria-label="Add link"]');
    $page->select('[aria-label="Link target"]', 'site.social');

    $page->assertScript("!!document.querySelector('[role=\"dialog\"]')", true)
        ->assertSee('Social link');

    $page->select('#dt-network', 'github');
    $page->click('button:has-text("Apply")');
    $page->click('[aria-label="Apply link"]');

    // A span, not an anchor, carrying the tag rather than a stored href.
    $page->assertScript(
        "document.querySelector('.prose-editor .editor-link')?.getAttribute('data-dynamic-tag')",
        'site.social',
    );
});

it('reopens an existing dynamic link with its tag preselected', function () {
    config(['site.social' => ['github' => 'https://github.com/a-distinctive-account']]);

    $page = visit('/new/note');

    selectTypedLine($page, 'my github');
    $page->click('[aria-label="Add link"]');
    $page->select('[aria-label="Link target"]', 'site.social');
    $page->select('#dt-network', 'github');
    $page->click('button:has-text("Apply")');
    $page->click('[aria-label="Apply link"]');

    $page->click('.prose-editor .editor-link');
    $page->click('[aria-label="Edit link"]');

    $page->assertScript("document.querySelector('[aria-label=\"Link target\"]').value", 'site.social');
});
