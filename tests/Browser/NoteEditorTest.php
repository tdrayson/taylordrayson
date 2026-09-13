<?php

use App\Models\Note;
use App\Models\User;
use App\Support\Links;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    // Saving a note runs ResolveLinkFavicons for each host it links to, and on
    // the sync queue that is a real HTTP call the suite refuses, which fails
    // the save rather than the fetch. A stored icon makes it a no-op.
    File::ensureDirectoryExists(dirname(Links::faviconPath('example.com')));
    File::put(Links::faviconPath('example.com'), file_get_contents(base_path('tests/Fixtures/pixel.webp')));
});

afterEach(fn () => File::delete(Links::faviconPath('example.com')));

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
