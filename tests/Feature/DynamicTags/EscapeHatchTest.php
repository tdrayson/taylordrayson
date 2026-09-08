<?php

use App\Support\PortableText;

it('converts a recognised token into a tag node', function () {
    $blocks = PortableText::fromPlainText('I have logged {entries.count type:note} things.');
    $children = $blocks[0]['children'];

    expect($children[0]['text'])->toBe('I have logged ')
        ->and($children[1])->toMatchArray([
            '_type' => 'dynamicTag',
            'tag' => 'entries.count',
            'options' => ['type' => 'note'],
        ])
        ->and($children[2]['text'])->toBe(' things.');
});

it('converts a token with no options', function () {
    $children = PortableText::fromPlainText('{entries.count} things')[0]['children'];

    expect($children[0])->toMatchArray(['_type' => 'dynamicTag', 'tag' => 'entries.count', 'options' => []]);
});

it('leaves an unregistered tag as literal text', function () {
    $blocks = PortableText::fromPlainText('I have logged {entriez} things.');

    expect(PortableText::plainText($blocks))->toBe('I have logged {entriez} things.');
});

it('leaves ordinary braces alone', function () {
    expect(PortableText::plainText(PortableText::fromPlainText('Use {} for an empty set.')))
        ->toBe('Use {} for an empty set.');
});

it('recognises a three-and four-segment tag name alongside a two-segment one', function () {
    $blocks = PortableText::fromPlainText('{ambient.rings.move.goal} of {ambient.rings.move.percent}.');
    $children = $blocks[0]['children'];

    expect($children[0])->toMatchArray(['_type' => 'dynamicTag', 'tag' => 'ambient.rings.move.goal'])
        ->and($children[2])->toMatchArray(['_type' => 'dynamicTag', 'tag' => 'ambient.rings.move.percent']);
});

it('autolinks the plain text between two tags', function () {
    $blocks = PortableText::fromPlainText('{entries.count} see https://example.com {streak.current}');
    $children = $blocks[0]['children'];
    $linked = collect($children)->first(fn (array $child): bool => ($child['marks'] ?? []) !== []);

    expect($linked)->not->toBeNull()
        ->and($blocks[0]['markDefs'])->toHaveCount(1)
        ->and($blocks[0]['markDefs'][0]['href'])->toBe('https://example.com');
});
