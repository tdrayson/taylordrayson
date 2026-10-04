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
        PortableText::block('Deep heading', 'h4'),
        PortableText::block('Deeper heading', 'h6'),
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
        ['_type' => 'image', '_key' => PortableText::key(), 'url' => '/storage/1/local.webp', 'width' => 600, 'height' => 900],
    ]))->toBeTrue();
});

it('rejects malformed documents', function (mixed $document) {
    expect(ptPasses($document))->toBeFalse();
})->with([
    'not a list' => [['blocks' => []]],
    'unknown node type' => [[['_type' => 'embed', '_key' => 'k1']]],
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
    'image width not an integer' => [[['_type' => 'image', '_key' => 'k1', 'url' => 'https://example.com/a.webp', 'width' => '600']]],
    'image height zero' => [[['_type' => 'image', '_key' => 'k1', 'url' => 'https://example.com/a.webp', 'height' => 0]]],
    'image with protocol-relative url' => [[['_type' => 'image', '_key' => 'k1', 'url' => '//example.com/a.webp']]],
]);

it('accepts a markDef href that is a root-relative entry path, as the mention picker inserts', function () {
    $link = PortableText::key();

    expect(ptPasses([
        [
            '_type' => 'block', '_key' => PortableText::key(), 'style' => 'normal',
            'markDefs' => [['_key' => $link, '_type' => 'link', 'href' => '/2025/03/04/slug']],
            'children' => [PortableText::span('a mention', [$link])],
        ],
    ]))->toBeTrue();
});

it('accepts a markDef href using the mailto: scheme', function () {
    $link = PortableText::key();

    expect(ptPasses([
        [
            '_type' => 'block', '_key' => PortableText::key(), 'style' => 'normal',
            'markDefs' => [['_key' => $link, '_type' => 'link', 'href' => 'mailto:hello@example.com']],
            'children' => [PortableText::span('email me', [$link])],
        ],
    ]))->toBeTrue();
});

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

it('accepts a valid callout node', function () {
    $link = PortableText::key();

    expect(ptPasses([
        [
            '_type' => 'callout', '_key' => PortableText::key(), 'variant' => 'tip',
            'markDefs' => [['_key' => $link, '_type' => 'link', 'href' => 'https://example.com']],
            'children' => [PortableText::span('Bold '), PortableText::span('link', ['strong', $link])],
        ],
    ]))->toBeTrue();

    foreach (['note', 'tip', 'important', 'warning', 'caution'] as $variant) {
        expect(ptPasses([
            ['_type' => 'callout', '_key' => PortableText::key(), 'variant' => $variant, 'markDefs' => [], 'children' => [PortableText::span('Body text')]],
        ]))->toBeTrue();
    }
});

it('rejects malformed callout nodes', function (mixed $document) {
    expect(ptPasses($document))->toBeFalse();
})->with([
    'bad variant' => [[['_type' => 'callout', '_key' => 'k1', 'variant' => 'danger', 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => []]]]]],
    'missing variant' => [[['_type' => 'callout', '_key' => 'k1', 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => []]]]]],
    'no children' => [[['_type' => 'callout', '_key' => 'k1', 'variant' => 'note', 'markDefs' => [], 'children' => []]]],
    'bad mark on child' => [[['_type' => 'callout', '_key' => 'k1', 'variant' => 'note', 'markDefs' => [], 'children' => [['_type' => 'span', '_key' => 'k2', 'text' => 'x', 'marks' => ['underline-nope']]]]]],
]);

it('accepts a valid video node', function () {
    expect(ptPasses([
        ['_type' => 'video', '_key' => 'k1', 'url' => 'https://example.com/clip.mp4', 'caption' => 'Demo', 'width' => 1280, 'height' => 720],
    ]))->toBeTrue();

    expect(ptPasses([
        ['_type' => 'video', '_key' => 'k1', 'url' => '/storage/1/clip.mp4'],
    ]))->toBeTrue();
});

it('rejects malformed video nodes', function (mixed $document) {
    expect(ptPasses($document))->toBeFalse();
})->with([
    'missing url' => [[['_type' => 'video', '_key' => 'k1']]],
    'protocol-relative url' => [[['_type' => 'video', '_key' => 'k1', 'url' => '//example.com/clip.mp4']]],
    'width not an integer' => [[['_type' => 'video', '_key' => 'k1', 'url' => 'https://example.com/clip.mp4', 'width' => '1280']]],
    'width zero' => [[['_type' => 'video', '_key' => 'k1', 'url' => 'https://example.com/clip.mp4', 'width' => 0]]],
]);

it('accepts the decorators the editor actually emits', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [PortableText::span('struck', ['underline', 'strike-through'])],
    ]]))->toBeTrue();
});

it('accepts the nulls the editor writes for absent optionals', function () {
    expect(ptPasses([
        ['_type' => 'image', '_key' => 'i1', 'url' => '/storage/a.webp', 'width' => null, 'height' => null],
        ['_type' => 'code', '_key' => 'c1', 'code' => 'echo 1;', 'language' => null, 'filename' => null, 'lineNumbers' => null],
    ]))->toBeTrue();
});

it('accepts a plain string, since a Prose field may be saved without a document', function () {
    expect(ptPasses('Just a plain note.'))->toBeTrue();
});

it('accepts a list item the editor has just created with no text yet', function () {
    expect(ptPasses([
        ['_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'listItem' => 'bullet', 'level' => 1, 'children' => []],
    ]))->toBeTrue();
});

it('accepts a registered dynamic tag', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [
            PortableText::span('I have logged '),
            ['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'entries.count', 'options' => ['type' => 'note']],
        ],
    ]]))->toBeTrue();
});

it('rejects an unregistered tag, so a typo fails on save', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'entriez', 'options' => []]],
    ]]))->toBeFalse();
});

it('rejects a tag used in a placement it does not support', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'entries.photo', 'options' => []]],
    ]]))->toBeFalse();
});

it('accepts a tag legal in the placement it is used in', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'site.social', 'options' => ['network' => 'github']]],
    ]]))->toBeTrue();
});

it('accepts a dynamicHref markDef and a span referencing it', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal',
        'markDefs' => [['_key' => 'h1', '_type' => 'dynamicHref', 'tag' => 'site.social', 'options' => ['network' => 'github']]],
        'children' => [PortableText::span('GitHub', ['h1'])],
    ]]))->toBeTrue();
});

it('rejects a dynamicHref for a tag that does not support the placement', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal',
        'markDefs' => [['_key' => 'h1', '_type' => 'dynamicHref', 'tag' => 'entries.count', 'options' => []]],
        'children' => [PortableText::span('Count', ['h1'])],
    ]]))->toBeFalse();
});

it('accepts an image node carrying a tag instead of a url', function () {
    expect(ptPasses([
        ['_type' => 'image', '_key' => 'i1', 'tag' => 'entries.photo', 'options' => []],
    ]))->toBeTrue();
});

it('rejects an image tag not legal as an image source', function () {
    expect(ptPasses([
        ['_type' => 'image', '_key' => 'i1', 'tag' => 'entries.count', 'options' => []],
    ]))->toBeFalse();
});

it('rejects an unknown option value', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'entries.count', 'options' => ['type' => 'nope']]],
    ]]))->toBeFalse();
});

it('rejects an undeclared option name', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'entries.count', 'options' => ['nope' => 'x']]],
    ]]))->toBeFalse();
});

it('accepts a bare year for the period option', function () {
    expect(ptPasses([[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'entries.count', 'options' => ['period' => '2025']]],
    ]]))->toBeTrue();
});
