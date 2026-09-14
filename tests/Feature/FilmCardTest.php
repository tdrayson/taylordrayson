<?php

use App\Enums\TimelineType;
use App\Models\Film;
use App\Presenters\CardPresenter;

it('renders a card for a film', function () {
    $film = Film::factory()->create(['title' => 'Test Title', 'meta' => []]);

    $card = CardPresenter::for($film);

    expect($card->type)->toBe(TimelineType::Film)
        ->and($card->title)->toBe('Test Title');
});

it('says the film was watched and rated, with the overview as its summary', function () {
    $film = Film::factory()->make([
        'title' => 'Fall 2: Deadpoint',
        'rating' => 8,
        'overview' => 'Two climbers become trapped.',
        'meta' => ['year' => 2026, 'runtime' => 98, 'tmdb' => ['genres' => ['Thriller']]],
    ]);

    $card = CardPresenter::for($film);

    expect($card->title)->toBe('Fall 2: Deadpoint')
        ->and($card->subtitle)->toBe('I watched this 2026 film and rated it 8/10.')
        ->and($card->summary)->toBe('Two climbers become trapped.');
});

it('drops the year and rating clauses when they are unknown', function () {
    $film = Film::factory()->make(['rating' => null, 'overview' => null, 'meta' => []]);

    $card = CardPresenter::for($film);

    expect($card->subtitle)->toBe('I watched this film.')
        ->and($card->summary)->toBeNull();
});

it('builds a trakt content url for a film from meta ids, not the history id', function () {
    $film = Film::factory()->make([
        'source' => 'trakt',
        'source_id' => '99999',
        'meta' => ['ids' => ['slug' => 'dune-2021']],
    ]);

    expect($film->platform_url)->toBe('https://trakt.tv/movies/dune-2021');
});
