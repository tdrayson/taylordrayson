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

it('keeps every disc dark enough to carry its glyph', function () {
    // Wow and haha were yellow, where white read at 2.10:1 and 2.03:1 against
    // the 3:1 WCAG asks of a graphical object. Measured on the painted pixels
    // rather than the tokens, so a later theme edit cannot quietly undo it.
    $failing = <<<'JS'
    (() => {
        const channel = (v) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
        const luminance = (s) => {
            const [r, g, b] = s.match(/[\d.]+/g).slice(0, 3).map((n) => channel(n / 255));
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        return [...document.querySelectorAll('.reaction-pip')].filter((el) => {
            const style = getComputedStyle(el);
            const a = luminance(style.backgroundColor);
            const b = luminance(style.color);
            return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) < 3;
        }).length;
    })()
    JS;

    visit(noteWithEveryReaction()->url())
        ->assertPresent('.reaction-pip')
        ->assertScript($failing, 0);
});
