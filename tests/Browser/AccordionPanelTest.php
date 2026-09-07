<?php

use App\Models\Note;
use App\Support\PortableText;

/**
 * The response asides, which are the site's accordions in their quiet variant.
 *
 * A closed panel is clipped rather than display:none now, so it has a height to
 * grow from. That makes it reachable unless something says otherwise, which is
 * the property worth guarding: content nobody can see must stay out of the tab
 * order and the accessibility tree.
 */
function noteWithAsides(): Note
{
    return Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'content' => PortableText::fromPlainText('Something worth responding to.'),
    ]);
}

it('keeps a closed accordion out of the tab order', function () {
    visit(noteWithAsides()->url())
        ->assertPresent('.accordion-panel')
        ->assertScript(
            "[...document.querySelectorAll('.accordion-panel:not(.is-open) section')].every((el) => el.hasAttribute('inert'))",
            true,
        );
});

it('collapses a closed accordion to nothing, so it is not just transparent', function () {
    visit(noteWithAsides()->url())
        ->assertPresent('.accordion-panel')
        ->assertScript(
            "[...document.querySelectorAll('.accordion-panel:not(.is-open)')].every((el) => el.getBoundingClientRect().height === 0)",
            true,
        );
});

it('opens a panel when its control is pressed', function () {
    visit(noteWithAsides()->url())
        ->assertPresent('.accordion-panel')
        ->click('button:has-text("Reference this post")')
        ->assertScript("document.querySelectorAll('.accordion-panel.is-open').length > 0", true)
        ->assertScript(
            "[...document.querySelectorAll('.accordion-panel.is-open section')].every((el) => ! el.hasAttribute('inert'))",
            true,
        );
});
