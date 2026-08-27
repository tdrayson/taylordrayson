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

    expect(CardPresenter::for($media)->subtitle)->toContain('season 1 episode 3');
});

it('leads an episode card with the episode, and names the show in the subtitle', function () {
    $media = Media::factory()->make([
        'type' => 'episode',
        'title' => 'Pilot',
        'rating' => null,
        'meta' => ['season' => 1, 'episode' => 3, 'show_title' => 'Severance'],
    ]);

    $card = CardPresenter::for($media);

    // A day of one show would otherwise repeat the same title down the feed.
    expect($card->title)->toBe('Pilot')
        ->and($card->subtitle)->toBe('I watched Severance, season 1 episode 3.');
});

it('keeps the episode title in the heading when no show can be resolved', function () {
    $media = Media::factory()->make([
        'type' => 'episode',
        'title' => 'Pilot',
        'series_id' => null,
        'rating' => null,
        'meta' => ['season' => 1, 'episode' => 3],
    ]);

    $card = CardPresenter::for($media);

    // The fallback must not print "Pilot" as both the heading and the subtitle.
    expect($card->title)->toBe('Pilot')
        ->and($card->subtitle)->toBe('I watched this one, season 1 episode 3.');
});

it('leaves a film card titled by the film', function () {
    $media = Media::factory()->make([
        'type' => 'film',
        'title' => 'Dune',
        'rating' => 8,
        'meta' => ['year' => 2021],
    ]);

    $card = CardPresenter::for($media);

    expect($card->title)->toBe('Dune')
        ->and($card->subtitle)->toBe('I watched this 2021 film and rated it 8/10.');
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
