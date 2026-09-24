<?php

use App\Data\FilmMeta;
use App\Models\Film;

it('does not sprout keys the row never had', function () {
    // A film has no season/episode. Storing null for every unset field would
    // rewrite every film's meta with a dozen empty episode keys.
    $film = Film::factory()->create(['meta' => ['year' => 2026]]);

    $film->fresh()->touch();

    expect($film->fresh()->meta->toArray())->toBe(['year' => 2026]);
});

it('reads an empty meta column as an empty object, never null', function () {
    // So callers can say `$film->meta->year` without first proving meta exists.
    $film = Film::factory()->create(['meta' => []]);

    expect($film->fresh()->meta)->toBeInstanceOf(FilmMeta::class)
        ->and($film->fresh()->meta->year)->toBeNull()
        ->and($film->fresh()->meta->ids->slug)->toBeNull()
        ->and($film->fresh()->meta->tmdb->genres)->toBe([]);
});
