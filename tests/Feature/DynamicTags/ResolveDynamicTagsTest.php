<?php

use App\Actions\ResolveDynamicTags;
use App\Models\Note;
use App\Support\PortableText;

it('replaces an inline tag with a span carrying the resolved text', function () {
    Note::factory()->count(3)->create();

    $resolved = app(ResolveDynamicTags::class)(contentTagging('entries.count'));
    $child = $resolved[0]['children'][1];

    expect($child)->toMatchArray(['_type' => 'span', '_key' => 't1', 'text' => '3', 'marks' => []])
        ->and($child['dynamicTag'])->toMatchArray(['tag' => 'entries.count', 'value' => 3]);
});

it('survives into plain text, so feeds and excerpts keep the number', function () {
    Note::factory()->count(3)->create();

    $resolved = app(ResolveDynamicTags::class)(contentTagging('entries.count'));

    expect(PortableText::plainText($resolved))->toBe('I have logged 3 things.');
});

it('degrades an unresolvable tag to an empty span rather than a hole', function () {
    $resolved = app(ResolveDynamicTags::class)(contentTagging('entries.nope'));

    expect($resolved[0]['children'][1])->toMatchArray(['_type' => 'span', 'text' => ''])
        ->and(PortableText::plainText($resolved))->toBe('I have logged things.');
});

it('walks into callout children', function () {
    Note::factory()->count(3)->create();

    $document = [[
        '_type' => 'callout',
        '_key' => 'c1',
        'variant' => 'note',
        'children' => contentTagging('entries.count'),
    ]];

    expect(app(ResolveDynamicTags::class)($document)[0]['children'][0]['children'][1]['text'])
        ->toBe('3');
});

it('leaves a document with no tags untouched', function () {
    $document = [PortableText::block('Nothing dynamic here')];

    expect(app(ResolveDynamicTags::class)($document))->toBe($document);
});
