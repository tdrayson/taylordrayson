<?php

use App\Models\Checkin;
use App\Models\User;
use App\Queries\PhotoStream;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/** Inserts an empty image block via the slash menu. */
function insertImageBlock($page): void
{
    $page->click('.prose-editor')->typeSlowly('.prose-editor', '/image');
    $page->keys('.prose-editor', ['Enter']);
}

it('offers a dynamic photo tag on an empty image and marks the block as live once chosen', function () {
    $page = visit('/new/article');

    insertImageBlock($page);

    $page->assertScript("!!document.querySelector('[aria-label=\"Use Latest photo as this image\"]')", true);

    $page->click('[aria-label="Use Latest photo as this image"]');

    $page->assertScript("!!document.querySelector('.prose-editor figure[data-dynamic-tag=\"entries.photo\"]')", true)
        ->assertSee('Live photo');
});

it('resolves a tagged image to the real photo it stands in for', function () {
    config(['queue.default' => 'sync']); // run media conversions inline so the url is ready
    Storage::fake('public');

    $entry = Checkin::factory()->create(['occurred_at' => now()->subDay()]);
    $entry->addMediaFromString(fakeJpeg())->usingFileName('photo.jpg')->toMediaCollection('photos');

    $expectedUrl = app(PhotoStream::class)(1)[0]['full'];

    $page = visit('/new/article');

    insertImageBlock($page);
    $page->click('[aria-label="Use Latest photo as this image"]');

    $page->assertScript("document.querySelector('.prose-editor figure img')?.getAttribute('src')", $expectedUrl);
});

it('still accepts a typed url, carrying no dynamic tag', function () {
    $page = visit('/new/article');

    insertImageBlock($page);

    $page->fill('[placeholder="or paste an image URL"]', 'https://example.com/a.jpg');
    $page->click('button:has-text("Use")');

    $page->assertScript("document.querySelector('.prose-editor figure img')?.getAttribute('src')", 'https://example.com/a.jpg')
        ->assertScript("document.querySelector('.prose-editor figure').hasAttribute('data-dynamic-tag')", false);
});

it('removes a tagged image entirely on delete, like an ordinary one', function () {
    $page = visit('/new/article');

    insertImageBlock($page);
    $page->click('[aria-label="Use Latest photo as this image"]');
    $page->assertScript("!!document.querySelector('.prose-editor figure')", true);

    $page->click('[aria-label="Remove image"]');

    $page->assertScript("!!document.querySelector('.prose-editor figure')", false);
});
