<?php

use App\Support\PortableText;

function richDocument(): array
{
    return [
        ['_type' => 'block', '_key' => 'a', 'style' => 'h2', 'children' => [
            ['_type' => 'span', '_key' => 'a1', 'text' => 'A heading', 'marks' => []],
        ], 'markDefs' => []],
        ['_type' => 'block', '_key' => 'b', 'style' => 'normal', 'children' => [
            ['_type' => 'span', '_key' => 'b1', 'text' => 'Plain then ', 'marks' => []],
            ['_type' => 'span', '_key' => 'b2', 'text' => 'bold', 'marks' => ['strong']],
            ['_type' => 'span', '_key' => 'b3', 'text' => ' then ', 'marks' => []],
            ['_type' => 'span', '_key' => 'b4', 'text' => 'italic', 'marks' => ['em']],
            ['_type' => 'span', '_key' => 'b5', 'text' => ' then ', 'marks' => []],
            ['_type' => 'span', '_key' => 'b6', 'text' => 'a link', 'marks' => ['lnk']],
        ], 'markDefs' => [['_key' => 'lnk', '_type' => 'link', 'href' => 'https://example.test']]],
        ['_type' => 'block', '_key' => 'c', 'style' => 'normal', 'listItem' => 'bullet', 'level' => 1, 'children' => [
            ['_type' => 'span', '_key' => 'c1', 'text' => 'First', 'marks' => []],
        ], 'markDefs' => []],
        ['_type' => 'block', '_key' => 'd', 'style' => 'normal', 'listItem' => 'number', 'level' => 1, 'children' => [
            ['_type' => 'span', '_key' => 'd1', 'text' => 'Numbered', 'marks' => []],
        ], 'markDefs' => []],
        ['_type' => 'image', '_key' => 'e', 'url' => 'https://example.test/a.jpg', 'alt' => 'A picture'],
        ['_type' => 'code', '_key' => 'f', 'code' => 'echo "hi";'],
        ['_type' => 'divider', '_key' => 'g'],
    ];
}

it('renders every node type in the corpus as markdown', function () {
    $md = PortableText::markdown(richDocument());

    expect($md)->toContain('## A heading')
        ->and($md)->toContain('**bold**')
        ->and($md)->toContain('_italic_')
        ->and($md)->toContain('[a link](https://example.test)')
        ->and($md)->toContain('- First')
        ->and($md)->toContain('1. Numbered')
        ->and($md)->toContain('![A picture](https://example.test/a.jpg)')
        ->and($md)->toContain("```\necho \"hi\";\n```")
        ->and($md)->toContain('---');
});

it('renders every node type in the corpus as html', function () {
    $html = PortableText::html(richDocument());

    expect($html)->toContain('<h2>A heading</h2>')
        ->and($html)->toContain('<strong>bold</strong>')
        ->and($html)->toContain('<em>italic</em>')
        ->and($html)->toContain('<a href="https://example.test">a link</a>')
        ->and($html)->toContain('<ul><li>First</li></ul>')
        ->and($html)->toContain('<ol><li>Numbered</li></ol>')
        ->and($html)->toContain('<img src="https://example.test/a.jpg" alt="A picture">')
        ->and($html)->toContain('<pre><code>echo &quot;hi&quot;;</code></pre>')
        ->and($html)->toContain('<hr>');
});

it('escapes html in text rather than trusting it', function () {
    $doc = [['_type' => 'block', '_key' => 'a', 'style' => 'normal', 'children' => [
        ['_type' => 'span', '_key' => 'a1', 'text' => '<script>alert(1)</script>', 'marks' => []],
    ], 'markDefs' => []]];

    expect(PortableText::html($doc))->toContain('&lt;script&gt;')
        ->and(PortableText::html($doc))->not->toContain('<script>');
});

it('returns an empty string for a null document', function () {
    expect(PortableText::markdown(null))->toBe('')
        ->and(PortableText::html(null))->toBe('');
});
