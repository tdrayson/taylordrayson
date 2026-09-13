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

it('leaves a film card titled by the film', function () {
    $film = Film::factory()->make([
        'title' => 'Dune',
        'rating' => 8,
        'meta' => ['year' => 2021],
    ]);

    $card = CardPresenter::for($film);

    expect($card->title)->toBe('Dune')
        ->and($card->subtitle)->toBe('I watched this 2021 film and rated it 8/10.');
});

it('builds a trakt content url for a film from meta ids, not the history id', function () {
    $film = Film::factory()->make([
        'source' => 'trakt',
        'source_id' => '99999',
        'meta' => ['ids' => ['slug' => 'dune-2021']],
    ]);

    expect($film->platform_url)->toBe('https://trakt.tv/movies/dune-2021');
});
