<?php

use App\Enums\EntryStatus;
use App\Models\Article;
use App\Support\PortableText;

it('unlocks a private article and renders its body', function () {
    Article::factory()->create([
        'title' => 'Behind the door',
        'excerpt' => 'A public teaser',
        'content' => PortableText::fromPlainText('Only after the password'),
        'slug' => 'behind-the-door',
        'occurred_at' => '2026-06-15 09:00:00',
        'status' => EntryStatus::Private,
        'password' => 'hunter2',
    ]);

    // innerText is rendered text only, so the Inertia props JSON cannot satisfy it.
    visit('/2026/06/15/behind-the-door')
        ->assertPresent('[data-password-prompt]')
        ->assertScript("document.body.innerText.includes('Only after the password')", false)
        ->fill('#entry-password', 'hunter2')
        ->click('button:has-text("Unlock")')
        ->assertMissing('[data-password-prompt]')
        ->assertScript("document.body.innerText.includes('Only after the password')", true)
        ->assertNoJavascriptErrors();
});
