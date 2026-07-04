<?php

use App\Support\PortableText;

it('joins text block children into plain text', function () {
    $document = [
        PortableText::block('Hello world'),
        PortableText::block('Second paragraph'),
    ];

    expect(PortableText::plainText($document))->toBe('Hello world Second paragraph');
});

it('includes code node text in plain text', function () {
    $document = [
        PortableText::block('Intro'),
        ['_type' => 'code', '_key' => 'k1', 'code' => 'echo 1;'],
    ];

    expect(PortableText::plainText($document))->toBe('Intro echo 1;');
});

it('ignores marks when flattening spans to plain text', function () {
    $document = [
        [
            '_type' => 'block',
            '_key' => 'k1',
            'style' => 'normal',
            'markDefs' => [],
            'children' => [
                PortableText::span('bold text', ['strong']),
                PortableText::span(' plain'),
            ],
        ],
    ];

    expect(PortableText::plainText($document))->toBe('bold text plain');
});

it('parses a raw JSON string document for plain text', function () {
    $json = json_encode([PortableText::block('From JSON')]);

    expect(PortableText::plainText($json))->toBe('From JSON');
});

it('returns empty plain text for garbage or null input', function (array|string|null $document) {
    expect(PortableText::plainText($document))->toBe('');
})->with([
    'null' => [null],
    'not json' => ['not json'],
    'envelope object json' => ['{"blocks": []}'],
]);

it('passes through a bare node array unchanged', function () {
    $nodes = [PortableText::block('A node')];

    expect(PortableText::nodes($nodes))->toBe($nodes);
});

it('decodes a JSON string into a bare node array', function () {
    $nodes = [PortableText::block('A node')];
    $json = json_encode($nodes);

    expect(PortableText::nodes($json))->toBe($nodes);
});

it('normalises a non-list envelope object to an empty node array', function () {
    expect(PortableText::nodes('{"blocks": []}'))->toBe([]);
    expect(PortableText::nodes(null))->toBe([]);
});

it('converts an editor.js paragraph to a normal block', function () {
    $document = ['blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'Plain paragraph']],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes)->toHaveCount(1);
    expect($nodes[0]['_type'])->toBe('block');
    expect($nodes[0]['style'])->toBe('normal');
    expect($nodes[0]['children'][0]['text'])->toBe('Plain paragraph');
});

it('converts an editor.js header level 3+ to an h3 block', function () {
    $document = ['blocks' => [
        ['type' => 'header', 'data' => ['text' => 'Sub heading', 'level' => 3]],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes[0]['style'])->toBe('h3');
});

it('converts an editor.js header level 2 or below to an h2 block', function () {
    $document = ['blocks' => [
        ['type' => 'header', 'data' => ['text' => 'Top heading', 'level' => 2]],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes[0]['style'])->toBe('h2');
});

it('converts an editor.js list into flat listItem blocks with level', function () {
    $document = ['blocks' => [
        ['type' => 'list', 'data' => [
            'style' => 'unordered',
            'items' => [
                ['content' => 'First item', 'items' => [
                    ['content' => 'Nested item', 'items' => []],
                ]],
                'Second item',
            ],
        ]],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes)->toHaveCount(3);
    expect($nodes[0]['listItem'])->toBe('bullet');
    expect($nodes[0]['level'])->toBe(1);
    expect($nodes[0]['children'][0]['text'])->toBe('First item');
    expect($nodes[1]['listItem'])->toBe('bullet');
    expect($nodes[1]['level'])->toBe(2);
    expect($nodes[1]['children'][0]['text'])->toBe('Nested item');
    expect($nodes[2]['listItem'])->toBe('bullet');
    expect($nodes[2]['level'])->toBe(1);
    expect($nodes[2]['children'][0]['text'])->toBe('Second item');
});

it('converts an editor.js ordered list to number listItems', function () {
    $document = ['blocks' => [
        ['type' => 'list', 'data' => ['style' => 'ordered', 'items' => ['one']]],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes[0]['listItem'])->toBe('number');
});

it('converts an editor.js delimiter to a divider node', function () {
    $document = ['blocks' => [
        ['type' => 'delimiter', 'data' => []],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes[0]['_type'])->toBe('divider');
});

it('converts an editor.js image with file.url to an image node', function () {
    $document = ['blocks' => [
        ['type' => 'image', 'data' => ['file' => ['url' => 'https://example.com/a.jpg'], 'caption' => 'A caption']],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes[0]['_type'])->toBe('image');
    expect($nodes[0]['url'])->toBe('https://example.com/a.jpg');
    expect($nodes[0]['caption'])->toBe('A caption');
});

it('strips inline html from editor.js paragraph and header text', function () {
    $document = ['blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'A <b>bold</b> word']],
        ['type' => 'header', 'data' => ['text' => 'A <b>bold</b> heading', 'level' => 2]],
    ]];

    $nodes = PortableText::fromEditorJs($document);

    expect($nodes[0]['children'][0]['text'])->toBe('A bold word');
    expect($nodes[1]['children'][0]['text'])->toBe('A bold heading');
});
