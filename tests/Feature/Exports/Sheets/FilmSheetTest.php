<?php

use App\Enums\ExportFormat;
use App\Models\Film;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a film as its ticket stub, with the rating sized as stars', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'title' => 'The Hunger Games: The Ballad of Songbirds & Snakes',
        'rating' => 6,
        'meta' => ['year' => 2023, 'runtime' => 157],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('The Hunger Games: The Ballad of Songbirds')
        ->and($txt)->toContain('6 out of 10')
        ->and($txt)->toContain('***..')
        ->and($txt)->toContain('2023')
        ->and($txt)->toContain('2h 37m');
});

it('omits the rating stars when a film carries no rating', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'rating' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('out of 10')
        ->and($txt)->not->toContain('*');
});
