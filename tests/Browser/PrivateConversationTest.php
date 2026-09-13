<?php

use App\Enums\EntryStatus;
use App\Models\Article;
use App\Support\PortableText;

/** Rendered text only, so the Inertia props JSON cannot satisfy it. */
const WEBMENTION_SECTION = "document.body.innerText.includes('Written about this on your own site?')";

it('leaves the webmention section off an unlocked private entry', function () {
    Article::factory()->create([
        'title' => 'Behind the door',
        'content' => PortableText::fromPlainText('Only after the password'),
        'slug' => 'behind-the-door',
        'occurred_at' => '2026-06-15 09:00:00',
        'status' => EntryStatus::Private,
        'password' => 'hunter2',
    ]);

    visit('/2026/06/15/behind-the-door')
        ->fill('#entry-password', 'hunter2')
        ->click('button:has-text("Unlock")')
        ->assertPresent('#responses')
        ->assertScript(WEBMENTION_SECTION, false)
        ->assertNoJavascriptErrors();
});

it('shows the webmention section on a published entry', function () {
    Article::factory()->create([
        'title' => 'Out in the open',
        'content' => PortableText::fromPlainText('Anyone can read this'),
        'slug' => 'out-in-the-open',
        'occurred_at' => '2026-06-15 09:00:00',
        'status' => EntryStatus::Published,
    ]);

    visit('/2026/06/15/out-in-the-open')
        ->assertPresent('#responses')
        ->assertScript(WEBMENTION_SECTION, true)
        ->assertNoJavascriptErrors();
});
