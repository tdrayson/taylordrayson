<?php

use App\Models\Article;
use App\Rules\ValidPortableText;
use App\Support\PortableText;
use Illuminate\Support\Facades\Validator;

function ptPasses(mixed $document): bool
{
    return ! Validator::make(['content' => $document], ['content' => [new ValidPortableText]])->fails();
}

it('accepts documents our builders produce', function () {
    $link = PortableText::key();

    expect(ptPasses([
        PortableText::block('Heading', 'h2'),
        PortableText::block('Prose'),
        PortableText::block('Item', 'normal', 'bullet', 2),
        [
            '_type' => 'block', '_key' => PortableText::key(), 'style' => 'normal',
            'markDefs' => [['_key' => $link, '_type' => 'link', 'href' => 'https://example.com']],
            'children' => [PortableText::span('bold link', ['strong', $link])],
        ],
        ['_type' => 'code', '_key' => PortableText::key(), 'code' => 'echo 1;', 'language' => 'php'],
        ['_type' => 'divider', '_key' => PortableText::key()],
        ['_type' => 'image', '_key' => PortableText::key(), 'url' => 'https://example.com/a.webp', 'caption' => 'Cap'],
    ]))->toBeTrue();
});

it('rejects malformed documents', function (mixed $document) {
    expect(ptPasses($document))->toBeFalse();
})->with([
    'not a list' => [['blocks' => []]],
    'unknown node type' => [[['_type' => 'video', '_key' => 'k1']]],
    'block without children' => [[['_type' => 'block', '_key' => 'k1', 'style' => 'normal', 'markDefs' => [], 'children' => []]]],
    'unknown style' => [[['_type' => 'block', '_key' => 'k1', 'style' => 'h1', 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => []]]]]],
    'span missing text' => [[['_type' => 'block', '_key' => 'k1', 'style' => 'normal', 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'marks' => []]]]]],
    'unknown decorator mark' => [[['_type' => 'block', '_key' => 'k1', 'style' => 'normal', 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => ['underline-nope']]]]]],
    'mark referencing missing markDef' => [[['_type' => 'block', '_key' => 'k1', 'style' => 'normal', 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => ['ghostkey1234']]]]]],
    'bad listItem' => [[['_type' => 'block', '_key' => 'k1', 'style' => 'normal', 'listItem' => 'square', 'level' => 1, 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => []]]]]],
    'image without url' => [[['_type' => 'image', '_key' => 'k1', 'caption' => 'x']]],
    'code without code' => [[['_type' => 'code', '_key' => 'k1', 'language' => 'php']]],
    'missing _key' => [[['_type' => 'divider']]],
    'markDef with invalid href' => [[['_type' => 'block', '_key' => 'k1', 'style' => 'normal', 'markDefs' => [['_key' => 'l1', '_type' => 'link', 'href' => 'not a url']], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => ['l1']]]]]],
]);

it('accepts every stored article document', function () {
    Article::factory()->count(3)->create()->each(function ($article) {
        expect(ptPasses($article->content))->toBeTrue();
    });
});

it('accepts code nodes with filename and line numbers', function () {
    expect(ptPasses([
        ['_type' => 'code', '_key' => 'k1', 'code' => 'echo 1;', 'language' => 'php', 'filename' => 'app/demo.php', 'lineNumbers' => true],
    ]))->toBeTrue();
});

it('rejects malformed code node extras', function (mixed $document) {
    expect(ptPasses($document))->toBeFalse();
})->with([
    'non-string filename' => [[['_type' => 'code', '_key' => 'k1', 'code' => 'x', 'filename' => 123]]],
    'empty language' => [[['_type' => 'code', '_key' => 'k1', 'code' => 'x', 'language' => '']]],
    'non-bool lineNumbers' => [[['_type' => 'code', '_key' => 'k1', 'code' => 'x', 'lineNumbers' => 'yes']]],
]);
