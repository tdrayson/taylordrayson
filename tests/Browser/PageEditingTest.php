<?php

use App\Models\Page;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('saves an edit to an existing page', function () {
    $page = Page::factory()->create(['title' => 'Things to try', 'published' => false, 'slug' => 'things-to-try']);

    $browser = visit('/things-to-try?edit');
    $browser->click('.prose-editor')->typeSlowly('.prose-editor', 'Edited body.', 20);

    // The typing has to have reached the form before the click, or the save
    // races it and posts the document as it was loaded.
    $browser->assertScript("document.querySelector('.prose-editor').innerText.includes('Edited body.')", true);

    $browser->click('button:has-text("Save draft")');
    $browser->assertSee('Draft, only you can see this');

    // The whole record, not just what the page displays: the editor offers a
    // slug field, and opening it without one saved the slug back as empty,
    // which the column rejects and takes the whole save down with it.
    expect(collect($page->refresh()->content)->toJson())->toContain('Edited body.')
        ->and($page->slug)->toBe('things-to-try');
});

it('opens the editor with the page\'s own field values', function () {
    Page::factory()->create(['title' => 'Colophon', 'slug' => 'colophon', 'published' => false]);

    $browser = visit('/colophon?edit');

    $browser->assertScript("document.querySelector('#slug').value", 'colophon');
});
