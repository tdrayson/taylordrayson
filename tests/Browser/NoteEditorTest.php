<?php

use App\Models\Note;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('saves a note as portable text, with a pasted link marked up', function () {
    $browser = visit('/new/note');

    $browser->click('.prose-editor')->typeSlowly('.prose-editor', 'See https://example.com for more ', 20);
    $browser->assertScript("document.querySelector('.prose-editor').innerText.includes('example.com')", true);
    $browser->fill('#slug', 'a-note');
    $browser->click('button:has-text("Post")');

    // Wait for the redirect to the saved note before reading it back: the click
    // returns before the request has landed.
    $browser->assertScript("location.pathname !== '/new/note'", true);

    $note = Note::sole();
    $content = collect($note->content);

    // Stored as blocks, not a string, and the URL carries a link markDef rather
    // than sitting in the text as bare characters.
    expect($content->first()['_type'])->toBe('block')
        ->and($content->first()['markDefs'][0]['href'] ?? null)->toBe('https://example.com');
});

it('offers a note only the blocks its schema can hold', function () {
    $browser = visit('/new/note');

    $browser->click('.prose-editor')->typeSlowly('.prose-editor', '/', 20);

    // The slash menu is filtered by the schema, so a note cannot be offered a
    // heading, an image or a code block it has no node for.
    $browser->assertScript("
        [...document.querySelectorAll('[role=\"option\"]')]
            .map(el => el.innerText.split('\\n')[0])
            .some(label => ['Heading 2', 'Image', 'Code', 'Callout', 'Quote', 'Divider'].includes(label))
    ", false);

    $browser->assertScript("document.querySelectorAll('[role=\"option\"]').length > 0", true);
});

it('drops the block handles in a note', function () {
    $browser = visit('/new/note');

    $browser->click('.prose-editor')->typeSlowly('.prose-editor', 'A note.', 20);

    // Reordering is a long-form affair, and the controls have no margin to sit
    // in beside a bordered box.
    $browser->assertScript("document.querySelector('[aria-label=\"Insert a block below\"]') === null", true);
});

// A response is a claim about somebody else's post, so changing my mind has to
// be possible: an unclearable select would leave the note published as a reply
// to whatever was picked first.
it('lets a response be taken back after it has been chosen', function () {
    $browser = visit('/new/note');

    $browser->click('.prose-editor')->typeSlowly('.prose-editor', 'Second thoughts.', 20);
    $browser->select('#response_kind', 'reply');
    $browser->assertPresent('#response_url');

    $browser->select('#response_kind', '');
    $browser->assertMissing('#response_url');

    $browser->fill('#slug', 'second-thoughts');
    $browser->click('button:has-text("Post")');
    $browser->assertScript("location.pathname !== '/new/note'", true);

    expect(Note::sole()->response_kind)->toBeNull();
});
