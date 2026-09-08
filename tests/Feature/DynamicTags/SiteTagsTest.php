<?php

use App\Actions\ResolveDynamicTags;
use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;
use App\Support\PortableText;

it('resolves the email with the @ spelled out, inline only', function () {
    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('site.email', [])['text'])
        ->toBe(str_replace('@', '(at)', config('site.email')))
        ->not->toContain('@')
        ->and($registry->find('site.email')->supports())
        ->toBe([Placement::Inline]);
});

it('never puts the real address in a resolved document, so plainText stays harvester-safe too', function () {
    $document = [[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal', 'markDefs' => [],
        'children' => [
            PortableText::span('Reach me at '),
            ['_type' => 'dynamicTag', '_key' => 't1', 'tag' => 'site.email', 'options' => []],
        ],
    ]];

    $resolved = app(ResolveDynamicTags::class)($document);

    expect($resolved[0]['children'][1]['text'])->not->toContain('@')
        ->and(PortableText::plainText($resolved))->not->toContain('@');
});

it('resolves a social link', function () {
    expect(app(DynamicTagRegistry::class)->value('site.social', ['network' => 'github'])['text'])
        ->toBe(config('site.social.github'));
});

it('resolves no social link when the network option is missing, same as an unknown one', function () {
    expect(app(DynamicTagRegistry::class)->value('site.social', []))->toBeNull();
});

it('rewrites a dynamicHref markDef into a link', function () {
    $document = [[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal',
        'markDefs' => [['_type' => 'dynamicHref', '_key' => 'd1', 'tag' => 'site.social', 'options' => ['network' => 'github']]],
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'my GitHub', 'marks' => ['d1']]],
    ]];

    $resolved = app(ResolveDynamicTags::class)($document);

    expect($resolved[0]['markDefs'][0])
        ->toMatchArray(['_type' => 'link', '_key' => 'd1', 'href' => config('site.social.github')])
        ->and($resolved[0]['children'][0]['marks'])->toBe(['d1']);
});

it('drops an unresolvable markDef so the text survives unlinked', function () {
    $document = [[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal',
        'markDefs' => [['_type' => 'dynamicHref', '_key' => 'd1', 'tag' => 'site.social', 'options' => ['network' => 'nope']]],
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'my GitHub', 'marks' => ['d1']]],
    ]];

    expect(app(ResolveDynamicTags::class)($document)[0]['markDefs'])->toBe([]);
});

it('resolves a block carrying both an ordinary link and a dynamicHref markDef', function () {
    $document = [[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal',
        'markDefs' => [
            ['_type' => 'link', '_key' => 'l1', 'href' => 'https://example.com'],
            ['_type' => 'dynamicHref', '_key' => 'd1', 'tag' => 'site.social', 'options' => ['network' => 'github']],
        ],
        'children' => [
            ['_type' => 'span', '_key' => 's1', 'text' => 'example', 'marks' => ['l1']],
            ['_type' => 'span', '_key' => 's2', 'text' => ' and ', 'marks' => []],
            ['_type' => 'span', '_key' => 's3', 'text' => 'my GitHub', 'marks' => ['d1']],
        ],
    ]];

    $resolved = app(ResolveDynamicTags::class)($document)[0];

    expect($resolved['markDefs'])->toBe([
        ['_type' => 'link', '_key' => 'l1', 'href' => 'https://example.com'],
        ['_type' => 'link', '_key' => 'd1', 'href' => config('site.social.github')],
    ])
        ->and($resolved['children'][0]['marks'])->toBe(['l1'])
        ->and($resolved['children'][2]['marks'])->toBe(['d1']);
});
