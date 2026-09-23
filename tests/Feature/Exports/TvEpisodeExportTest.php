<?php

use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a tv episode as labelled fields in order', function () {
    $show = TvShow::factory()->create(['title' => 'The Expanse']);

    $episode = TvEpisode::factory()->create([
        'tv_show_id' => $show->id,
        'occurred_at' => '2026-09-13 21:00:00',
        'title' => 'Dulcinea',
        'rating' => 9,
        'meta' => ['season' => 1, 'episode' => 1],
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($episode);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['episode', 'show', 'date', 'season', 'number', 'rating', 'owner', 'ticket_ref'])
        ->and($export->field('episode')->display)->toBe('Dulcinea')
        ->and($export->field('show')->display)->toBe('The Expanse')
        ->and($export->field('date')->display)->toBe('13 Sep 2026')
        ->and($export->field('season')->display)->toBe('1')
        ->and($export->field('number')->display)->toBe('1')
        ->and($export->field('rating')->display)->toBe('9 out of 10')
        ->and($export->field('owner')->display)->toBe(config('identity.name'))
        ->and($export->field('ticket_ref')->raw)->toBe($episode->id);

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('show', 'type', 'day')
        ->and($export->links[0]->key)->toBe('show')
        ->and($export->links[0]->url)->toEndWith($show->url());
});

it('offers neither geojson nor ics for a tv episode', function () {
    $episode = TvEpisode::factory()->create(['occurred_at' => '2026-09-13 21:00:00', 'status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($episode)));

    expect($formats)->not->toContain('geojson')
        ->and($formats)->not->toContain('ics');
});

it('never leaks an id, a timestamp or a password', function () {
    $episode = TvEpisode::factory()->create(['occurred_at' => '2026-09-13 21:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($episode)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
