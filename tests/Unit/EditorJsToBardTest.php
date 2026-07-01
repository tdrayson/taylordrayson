<?php

use App\Support\EditorJsToBard;

it('converts an editor.js paragraph block to a bard paragraph node', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'Hello <b>world</b>']],
    ]]);
    expect($bard[0]['type'])->toBe('paragraph');
    expect($bard[0]['content'][0]['text'])->toContain('Hello');
});

it('converts a header block to a bard heading node', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'header', 'data' => ['text' => 'My Heading', 'level' => 2]],
    ]]);
    expect($bard[0]['type'])->toBe('heading');
    expect($bard[0]['attrs']['level'])->toBe(2);
    expect($bard[0]['content'][0]['text'])->toBe('My Heading');
});

it('converts an unordered list block to a bard bulletList node', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['one', 'two', 'three']]],
    ]]);
    expect($bard[0]['type'])->toBe('bulletList');
    expect($bard[0]['content'])->toHaveCount(3);
    expect($bard[0]['content'][0]['type'])->toBe('listItem');
});

it('converts an ordered list block to a bard orderedList node', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'list', 'data' => ['style' => 'ordered', 'items' => ['first', 'second']]],
    ]]);
    expect($bard[0]['type'])->toBe('orderedList');
    expect($bard[0]['content'])->toHaveCount(2);
});

it('converts a quote block to a bard blockquote node', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'quote', 'data' => ['text' => 'To be or not to be']],
    ]]);
    expect($bard[0]['type'])->toBe('blockquote');
    expect($bard[0]['content'][0]['type'])->toBe('paragraph');
});

it('converts an image block to a bard image node', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'image', 'data' => ['file' => ['url' => 'https://example.com/photo.jpg'], 'caption' => 'A photo']],
    ]]);
    expect($bard[0]['type'])->toBe('image');
    expect($bard[0]['attrs']['src'])->toBe('https://example.com/photo.jpg');
    expect($bard[0]['attrs']['alt'])->toBe('A photo');
});

it('emits a paragraph for unknown block types and continues', function () {
    $bard = EditorJsToBard::convert(['blocks' => [
        ['type' => 'unknown_widget', 'data' => ['text' => 'some content']],
        ['type' => 'paragraph', 'data' => ['text' => 'normal paragraph']],
    ]]);
    expect($bard)->toHaveCount(2);
    expect($bard[0]['type'])->toBe('paragraph');
    expect($bard[1]['type'])->toBe('paragraph');
});

it('returns an empty array for an empty document', function () {
    expect(EditorJsToBard::convert(['blocks' => []]))->toBe([]);
    expect(EditorJsToBard::convert([]))->toBe([]);
});
