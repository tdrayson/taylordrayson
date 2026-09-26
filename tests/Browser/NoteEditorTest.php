<?php

use App\Models\Citation;
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
    $browser->click('aside button:has-text("Post")');

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
    $browser->click('button[aria-pressed]:has-text("Response")');
    $browser->select('#response_kind', 'reply');
    $browser->assertPresent('#response_url');

    $browser->select('#response_kind', '');
    $browser->assertMissing('#response_url');

    $browser->fill('#slug', 'second-thoughts');
    $browser->click('aside button:has-text("Post")');
    $browser->assertScript("location.pathname !== '/new/note'", true);

    expect(Note::sole()->response_kind)->toBeNull();
});

it('names a like after the post it likes and posts it with no body', function () {
    // Stored so the preview answers without fetching the page.
    Citation::factory()->create(['url' => 'https://example.com', 'title' => 'Example Domain']);

    $browser = visit('/new/note');
    $browser->click('button[aria-pressed]:has-text("Response")');
    $browser->select('#response_kind', 'like');
    $browser->fill('#response_url', 'https://example.com');
    $browser->click('#slug');

    $browser->assertScript("new Promise(r => setTimeout(() => r(document.querySelector('#slug').getAttribute('placeholder')), 1000))", 'liked-example-domain');
    $browser->assertScript(
        "[...document.querySelectorAll('p')].some((p) => /^\\/\\d{4}\\/\\d{2}\\/\\d{2}\\/liked-example-domain$/.test(p.textContent.trim()))",
        true,
    );

    $browser->click('aside button:has-text("Post")');
    $browser->assertScript("location.pathname !== '/new/note'", true);

    expect(Note::sole()->getAttributes()['slug'])->toBe('liked-example-domain');
});
