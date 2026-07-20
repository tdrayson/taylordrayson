<?php

use App\Models\Media;
use App\Presenters\CardPresenter;
use App\Timeline\TypeRegistry;

it('renders an episode card from canonical meta', function () {
    $media = Media::factory()->make([
        'type' => 'episode',
        'title' => 'Pilot',
        'rating' => null,
        'meta' => ['season' => 1, 'episode' => 3, 'show_title' => 'Severance'],
    ]);

    expect(CardPresenter::for($media)->subtitle)->toContain('S01E03');
});

it('builds a trakt content url for a film from meta ids, not the history id', function () {
    $media = Media::factory()->make([
        'type' => 'film',
        'source' => 'trakt',
        'source_id' => '99999',
        'meta' => ['ids' => ['slug' => 'dune-2021']],
    ]);

    expect($media->platform_url)->toBe('https://trakt.tv/movies/dune-2021');
});

it('maps the tv taxonomy to the canonical episode type only', function () {
    $definition = TypeRegistry::all()['media'];
    $taxonomy = $definition['taxonomy'];
    $query = Media::query();
    $taxonomy['filter']($query, 'tv');

    // The plan's original assertion (`toContain('type')` on the raw SQL) is a
    // tautology: any whereIn('type', ...) call satisfies it regardless of which
    // values are bound. Asserting the actual bound values meaningfully verifies
    // the 'tv' taxonomy resolves to the canonical ['episode'] type only, not the
    // legacy ['tv', 'tv_episode'] pair.
    expect($query->getBindings())->toBe(['episode']);
});
