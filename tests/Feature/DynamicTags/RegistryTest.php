<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;

it('finds a registered tag by name', function () {
    $registry = app(DynamicTagRegistry::class);

    expect($registry->find('entries.count'))->not->toBeNull()
        ->and($registry->find('entries.nope'))->toBeNull();
});

it('reports the placements a tag is legal in', function () {
    expect(app(DynamicTagRegistry::class)->find('entries.count')->supports())
        ->toContain(Placement::Inline);
});

it('resolves a tag once per request', function () {
    $registry = app(DynamicTagRegistry::class);

    $first = $registry->value('entries.count', []);
    $second = $registry->value('entries.count', []);

    expect($first)->toBe($second)
        ->and($first)->toHaveKeys(['value', 'text']);
});
