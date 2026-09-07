<?php

use App\Enums\ReactionType;
use App\Models\Note;
use App\Support\PortableText;

/**
 * The reaction discs, as they actually paint.
 *
 * Two properties neither a unit test nor the microformats tests can see: that
 * every glyph name resolves to a real icon, and that the glyph stays legible on
 * the disc behind it.
 */
function noteWithEveryReaction(): Note
{
    $note = Note::factory()->create([
        'occurred_at' => '2024-03-06 09:00:00',
        'content' => PortableText::fromPlainText('Something worth reacting to.'),
    ]);

    foreach (ReactionType::cases() as $index => $type) {
        $note->reactions()->create([
            'type' => $type,
            'identity_key' => hash('sha256', 'glyph-test-'.$index),
        ]);
    }

    return $note;
}

it('resolves an icon for every reaction, so no disc paints empty', function () {
    // Icon.vue renders nothing at all when a name is missing from the registry,
    // and a production build prints no warning about it.
    visit(noteWithEveryReaction()->url())
        ->assertPresent('.reaction-pip')
        ->assertScript('document.querySelectorAll(".reaction-pip svg").length', count(ReactionType::cases()));
});

it('darkens the glyph on the yellow discs, where white is unreadable', function () {
    // White reads at 2.1:1 on these two, against the 3:1 WCAG asks of a
    // graphical object. Ink on the same yellow reads at 7.6:1.
    $colourOf = fn (int $index): string => "getComputedStyle(document.querySelectorAll('.reaction-pip')[{$index}]).color";

    $page = visit(noteWithEveryReaction()->url())->assertPresent('.reaction-pip');

    // Order follows ReactionType: like, love, celebrate, wow, haha, sad.
    $page->assertScript($colourOf(3), 'rgb(34, 34, 34)')
        ->assertScript($colourOf(4), 'rgb(34, 34, 34)')
        ->assertScript($colourOf(0), 'rgb(255, 255, 255)');
});
