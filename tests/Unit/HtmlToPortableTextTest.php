<?php

use App\Rules\ValidPortableText;
use App\Support\HtmlToPortableText;

/** The text of every span in a node, marks ignored. */
function nodeText(array $node): string
{
    return implode('', array_column($node['children'] ?? [], 'text'));
}

function expectValid(array $document): void
{
    $error = null;
    (new ValidPortableText)->validate('content', $document, function (string $message) use (&$error): void {
        $error = $message;
    });

    expect($error)->toBeNull();
}

it('converts paragraphs and headings to blocks', function () {
    $nodes = HtmlToPortableText::convert('<h2>Title</h2><p>Body text.</p><h3>Sub</h3>');

    expect($nodes)->toHaveCount(3)
        ->and($nodes[0]['style'])->toBe('h2')
        ->and(nodeText($nodes[0]))->toBe('Title')
        ->and($nodes[1]['style'])->toBe('normal')
        ->and($nodes[2]['style'])->toBe('h3');

    expectValid($nodes);
});

it('demotes h1 to h2, the highest style the dialect has', function () {
    expect(HtmlToPortableText::convert('<h1>Shouted</h1>')[0]['style'])->toBe('h2');
});

it('drops the bold an editor wrapped a whole heading in', function () {
    $marks = HtmlToPortableText::convert('<h2><strong>Bold heading</strong></h2>')[0]['children'][0]['marks'];

    expect($marks)->toBe([]);
});

it('keeps decorators and links inside a paragraph', function () {
    $nodes = HtmlToPortableText::convert(
        '<p>Some <strong>bold</strong> and a <a href="https://example.com/a">link</a>.</p>',
    );

    $block = $nodes[0];
    $marked = collect($block['children'])->firstWhere('text', 'bold');
    $linked = collect($block['children'])->firstWhere('text', 'link');

    expect(nodeText($block))->toBe('Some bold and a link.')
        ->and($marked['marks'])->toBe(['strong'])
        ->and($block['markDefs'])->toHaveCount(1)
        ->and($block['markDefs'][0]['href'])->toBe('https://example.com/a')
        ->and($linked['marks'])->toBe([$block['markDefs'][0]['_key']]);

    expectValid($nodes);
});

it('leaves text unlinked when the href is not a valid URL', function () {
    $nodes = HtmlToPortableText::convert('<p>See <a href="/relative">this</a>.</p>');

    expect($nodes[0]['markDefs'])->toBe([])
        ->and(nodeText($nodes[0]))->toBe('See this.');

    expectValid($nodes);
});

it('flattens nested lists into levelled list items', function () {
    $nodes = HtmlToPortableText::convert('<ul><li>One<ul><li>Deeper</li></ul></li><li>Two</li></ul><ol><li>First</li></ol>');

    expect($nodes)->toHaveCount(4)
        ->and($nodes[0])->toMatchArray(['listItem' => 'bullet', 'level' => 1])
        ->and($nodes[1])->toMatchArray(['listItem' => 'bullet', 'level' => 2])
        ->and(nodeText($nodes[1]))->toBe('Deeper')
        ->and($nodes[2])->toMatchArray(['listItem' => 'bullet', 'level' => 1])
        ->and($nodes[3])->toMatchArray(['listItem' => 'number', 'level' => 1]);

    expectValid($nodes);
});

it('turns a figure into an image node carrying its caption and dimensions', function () {
    $nodes = HtmlToPortableText::convert(
        '<figure><img src="https://example.com/a.jpg" alt="alt text" width="800" height="600"><figcaption>A caption</figcaption></figure>',
    );

    expect($nodes[0])->toMatchArray([
        '_type' => 'image',
        'url' => 'https://example.com/a.jpg',
        'caption' => 'A caption',
        'width' => 800,
        'height' => 600,
    ]);

    expectValid($nodes);
});

it('does not borrow alt text as a caption, which would print one that never showed', function () {
    $node = HtmlToPortableText::convert('<figure><img src="https://example.com/a.jpg" alt="alt text"></figure>')[0];

    expect($node)->not->toHaveKey('caption');
});

it('normalises a YouTube embed to a video node with the watch URL', function () {
    $nodes = HtmlToPortableText::convert(
        '<figure><iframe src="https://www.youtube.com/embed/WfKNVgvZA74?feature=oembed"></iframe></figure>',
    );

    expect($nodes[0])->toMatchArray([
        '_type' => 'video',
        'url' => 'https://www.youtube.com/watch?v=WfKNVgvZA74',
    ]);

    expectValid($nodes);
});

it('walks through wrappers with no equivalent to the content inside them', function () {
    $nodes = HtmlToPortableText::convert('<div class="columns"><div class="column"><p>Inside</p></div></div>');

    expect($nodes)->toHaveCount(1)
        ->and(nodeText($nodes[0]))->toBe('Inside');
});

it('keeps the text of an unknown element rather than dropping it', function () {
    $nodes = HtmlToPortableText::convert('<table><tr><td>A cell</td></tr></table>');

    expect($nodes)->toHaveCount(1)
        ->and(nodeText($nodes[0]))->toBe('A cell');
});

it('collapses HTML whitespace and drops blocks left with nothing in them', function () {
    $nodes = HtmlToPortableText::convert("<p>\n    Spread   over\n    lines\n</p><p>&nbsp;</p><p></p>");

    expect($nodes)->toHaveCount(1)
        ->and(nodeText($nodes[0]))->toBe('Spread over lines');
});

it('returns nothing for empty or unrecognised markup', function () {
    expect(HtmlToPortableText::convert(''))->toBe([])
        ->and(HtmlToPortableText::convert('<script>alert(1)</script>'))->toBe([]);
});
