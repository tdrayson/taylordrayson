<?php

use App\Enums\TimelineType;
use App\Models\TvEpisode;
use App\Presenters\CardPresenter;

it('renders a card for an episode', function () {
    $episode = TvEpisode::factory()->create(['title' => 'Test Title', 'meta' => []]);

    $card = CardPresenter::for($episode);

    expect($card->type)->toBe(TimelineType::TvEpisode)
        ->and($card->title)->toBe('Test Title');
});

it('builds the episode card from canonical meta', function () {
    // tv_show_id null: the tvShow relation would otherwise win over
    // meta.show_title, which is what this asserts on.
    $episode = TvEpisode::factory()->make([
        'title' => 'Pilot',
        'tv_show_id' => null,
        'rating' => null,
        'meta' => ['season' => 1, 'episode' => 3, 'show_title' => 'Severance'],
    ]);

    expect(CardPresenter::for($episode)->subtitle)->toContain('season 1 episode 3');
});

it('leads an episode card with the episode, and names the show in the subtitle', function () {
    $episode = TvEpisode::factory()->make([
        'title' => 'Pilot',
        'tv_show_id' => null,
        'rating' => null,
        'meta' => ['season' => 1, 'episode' => 3, 'show_title' => 'Severance'],
    ]);

    $card = CardPresenter::for($episode);

    // A day of one show would otherwise repeat the same title down the feed.
    expect($card->title)->toBe('Pilot')
        ->and($card->subtitle)->toBe('I watched season 1 episode 3 of Severance.');
});

it('keeps the episode title in the heading when no show can be resolved', function () {
    $episode = TvEpisode::factory()->make([
        'title' => 'Pilot',
        'tv_show_id' => null,
        'rating' => null,
        'meta' => ['season' => 1, 'episode' => 3],
    ]);

    $card = CardPresenter::for($episode);

    // The fallback must not print "Pilot" as both the heading and the subtitle.
    expect($card->title)->toBe('Pilot')
        ->and($card->subtitle)->toBe('I watched season 1 episode 3.');
});

it('builds the tv episode detail from meta using the model, not a phantom "tv" value', function () {
    $episode = TvEpisode::factory()->create([
        'meta' => ['season' => 2, 'episode' => 5],
    ]);

    expect(CardPresenter::for($episode)->subtitle)->toContain('season 2 episode 5');
});
