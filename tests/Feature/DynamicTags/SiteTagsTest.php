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
