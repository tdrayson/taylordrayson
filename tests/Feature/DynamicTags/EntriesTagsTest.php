<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Models\Article;
use App\Models\Note;

// Article gates its timeline entry on `published` (fake()->boolean(90) in the
// factory), so it is pinned true here: otherwise the count this tag reads
// would be flaky against the fixture the test just created.
it('counts every timeline entry', function () {
    Article::factory()->count(2)->create(['published' => true]);
    Note::factory()->count(3)->create();

    expect(app(DynamicTagRegistry::class)->value('entries.count', []))
        ->toMatchArray(['value' => 5, 'text' => '5']);
});

it('counts one type when given one', function () {
    Article::factory()->count(2)->create(['published' => true]);
    Note::factory()->count(3)->create();

    expect(app(DynamicTagRegistry::class)->value('entries.count', ['type' => 'note'])['value'])
        ->toBe(3);
});

it('formats a large count with thousands separators', function () {
    $tag = app(DynamicTagRegistry::class)->find('entries.count');

    expect($tag->format(10389, []))->toBe('10,389');
});

it('returns null for an unknown type', function () {
    expect(app(DynamicTagRegistry::class)->value('entries.count', ['type' => 'nope']))
        ->toBeNull();
});
