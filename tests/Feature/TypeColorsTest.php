<?php

use App\Enums\TimelineType;
use App\Support\TypeColors;

/*
 * The accents are parsed out of the stylesheet so there is no second list to
 * keep in step. The cost of that is a silent failure mode: move the palette to
 * another file and every lookup falls back to the brand colour, which is a
 * plausible-looking blue rather than an error. That is what these assert.
 */

it('resolves a real colour for every timeline type', function () {
    foreach (TimelineType::cases() as $case) {
        expect(TypeColors::hex($case->accent()))
            ->toMatch('/^[0-9a-f]{6}$/')
            ->not->toBe('3858e9', "The {$case->value} accent fell back to the brand colour, so the palette was not found.");
    }
});

it('gives each type its own colour rather than one shared default', function () {
    $accents = array_map(fn (TimelineType $case): string => TypeColors::hex($case->accent()), TimelineType::cases());

    expect(array_unique($accents))->toHaveCount(count($accents));
});

it('reads the light palette, not the dark one', function () {
    // theme.css has fuel at 48% lightness, dark.css at 58%. An OG card is drawn
    // on a light ground whatever the reader's theme is.
    expect(TypeColors::hex('fuel'))->toBe('e2ae12');
});

it('falls back for a token that has no colour', function () {
    expect(TypeColors::hex('not-a-type'))->toBe('3858e9');
});
