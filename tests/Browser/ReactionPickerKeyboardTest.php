<?php

use App\Models\Note;
use App\Support\PortableText;

/**
 * The reaction picker is a menu a keyboard opens on purpose. Tabbing through
 * the bar must not throw it open, and pressing the control must not react
 * straight away, since a keyboard has no hover to choose with first.
 */
it('opens the picker on Enter rather than on focus, and moves into it', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-06 09:00:00',
        'content' => PortableText::fromPlainText('Something worth reacting to.'),
    ]);

    $control = '[data-testid="reaction-bar"] [aria-haspopup]';
    $page = visit($note->url())->assertPresent($control);

    $page->script("document.querySelector('{$control}').focus()");
    $page->assertScript("document.querySelector('{$control}').getAttribute('aria-expanded')", 'false');

    $page->keys($control, 'Enter')
        ->assertScript("document.querySelector('{$control}').getAttribute('aria-expanded')", 'true')
        ->assertScript("document.activeElement.closest('[data-picker]') !== null", true)
        ->assertScript("document.querySelector('{$control}').getAttribute('aria-pressed')", 'false');
});
