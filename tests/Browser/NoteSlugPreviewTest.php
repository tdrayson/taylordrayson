<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('previews the slug and url a note would get, as it is typed', function () {
    $page = visit('/new/note');

    $page->click('.prose-editor')->type('.prose-editor', 'Everything went wrong today');
    // The editor emits its document a tick after the keystrokes land.
    $page->wait(1);

    // The placeholder is what the slug will be if the field is left alone, so
    // it has to follow the note rather than sit on a static default.
    $page->assertScript(
        "document.querySelector('#slug').getAttribute('placeholder')",
        'everything-went-wrong-today',
    );

    // And the line under it spells out the URL, dated with the day the entry
    // will be stamped with on save.
    $page->assertScript(
        "[...document.querySelectorAll('p')].some((p) => /^\\/\\d{4}\\/\\d{2}\\/\\d{2}\\/everything-went-wrong-today$/.test(p.textContent.trim()))",
        true,
    );
});

it('slugifies the slug field as it is typed, never holding a space', function () {
    $page = visit('/new/note');

    $page->click('.prose-editor')->type('.prose-editor', 'A note');
    $page->type('#slug', 'my custom slug');

    $page->assertScript("document.querySelector('#slug').value", 'my-custom-slug');
});

it('shows the fallback when the note has no words to slug', function () {
    $page = visit('/new/note');

    $page->click('.prose-editor')->type('.prose-editor', '👍');
    $page->wait(1);

    $page->assertScript("document.querySelector('#slug').getAttribute('placeholder')", 'note');
});
