<?php

use App\Models\Book;
use App\Models\Concerns\Timelineable;
use App\Models\Film;
use App\Models\TvEpisode;
use App\Presenters\CardPresenter;

/** The card's standalone line, the one EntryDescription publishes. */
function cardDescription(Timelineable $model): string
{
    return CardPresenter::card($model)->description($model);
}

it('names the film it describes, with the rating as 8/10', function (?int $rating, ?string $overview, string $expected) {
    $film = Film::factory()->make([
        'title' => 'Fall 2: Deadpoint',
        'rating' => $rating,
        'overview' => $overview,
        'meta' => ['year' => 2026],
    ]);

    expect(cardDescription($film))->toBe($expected);
})->with([
    'rated' => [8, null, 'I watched Fall 2: Deadpoint and rated it 8/10.'],
    'unrated' => [null, null, 'I watched Fall 2: Deadpoint.'],
    'overview wins' => [8, 'Two climbers become trapped.', 'Two climbers become trapped.'],
]);

it('names the book and its author', function (?string $author, ?int $rating, ?string $overview, string $expected) {
    $book = Book::factory()->make([
        'title' => 'Project Hail Mary',
        'rating' => $rating,
        'overview' => $overview,
        'meta' => ['author' => $author],
    ]);

    expect(cardDescription($book))->toBe($expected);
})->with([
    'rated, with author' => ['Andy Weir', 9, null, 'I read Project Hail Mary by Andy Weir and rated it 9/10.'],
    'unrated, no author' => [null, null, null, 'I read Project Hail Mary.'],
    'overview wins' => ['Andy Weir', 9, 'Ryland Grace is the sole survivor.', 'Ryland Grace is the sole survivor.'],
]);

it('names the episode by its show and place in the run', function (array $meta, ?int $rating, ?string $overview, string $expected) {
    // tv_show_id null: the tvShow relation would otherwise win over meta.show_title.
    $episode = TvEpisode::factory()->make([
        'title' => 'Pilot',
        'tv_show_id' => null,
        'rating' => $rating,
        'overview' => $overview,
        'meta' => $meta,
    ]);

    expect(cardDescription($episode))->toBe($expected);
})->with([
    'show and place, rated' => [['show_title' => 'Ted Lasso', 'season' => 4, 'episode' => 6], 9, null, 'I watched season 4 episode 6 of Ted Lasso and rated it 9/10.'],
    'show only' => [['show_title' => 'Formula 1'], null, null, 'I watched an episode of Formula 1.'],
    'no show' => [['season' => 1, 'episode' => 3], null, null, 'I watched Pilot.'],
    'overview wins' => [['show_title' => 'Ted Lasso'], 9, "It's New Year's Eve!", "It's New Year's Eve!"],
]);
