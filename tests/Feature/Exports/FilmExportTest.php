<?php

use App\Models\Film;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a film as labelled fields in order', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'title' => 'Arrival',
        'rating' => 8,
        'meta' => ['year' => 2016, 'runtime' => 116],
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($film);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['film', 'date', 'rating', 'year', 'runtime', 'owner', 'ticket_ref'])
        ->and($export->field('film')->display)->toBe('Arrival')
        ->and($export->field('date')->display)->toBe('13 Sep 2026')
        ->and($export->field('rating')->display)->toBe('8 out of 10')
        ->and($export->field('year')->display)->toBe('2016')
        ->and($export->field('year')->raw)->toBe(2016)
        ->and($export->field('runtime')->display)->toBe('1h 56m')
        ->and($export->field('owner')->display)->toBe(config('identity.name'))
        ->and($export->field('ticket_ref')->raw)->toBe($film->id);

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('type', 'day');
});

it('offers neither geojson nor ics for a film', function () {
    $film = Film::factory()->create(['occurred_at' => '2026-09-13 20:00:00', 'status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($film)));

    expect($formats)->not->toContain('geojson')
        ->and($formats)->not->toContain('ics');
});

it('never leaks an id, a timestamp or a password', function () {
    $film = Film::factory()->create(['occurred_at' => '2026-09-13 20:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($film)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
