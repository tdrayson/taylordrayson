<?php

use App\Actions\ResolveDynamicTags;
use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;

it('resolves the email as text and as a mailto href', function () {
    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('site.email', [])['text'])->toBe(config('site.email'))
        ->and($registry->find('site.email')->supports())
        ->toContain(Placement::Inline, Placement::Href);
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

it('gives an email markDef a mailto href rather than the bare address', function () {
    $document = [[
        '_type' => 'block', '_key' => 'b1', 'style' => 'normal',
        'markDefs' => [['_type' => 'dynamicHref', '_key' => 'd1', 'tag' => 'site.email', 'options' => []]],
        'children' => [['_type' => 'span', '_key' => 's1', 'text' => 'email me', 'marks' => ['d1']]],
    ]];

    expect(app(ResolveDynamicTags::class)($document)[0]['markDefs'][0]['href'])
        ->toBe('mailto:'.config('site.email'));
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
