<?php

use App\Presenters\ExportPresenter;

it('publishes a flight as labelled fields in order', function () {
    $export = ExportPresenter::for(krkToLgw());

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['flight', 'origin', 'destination', 'departed', 'arrived', 'duration', 'distance', 'cabin', 'reason']);
});

it('formats each flight field for a reader and keeps the machine value in raw', function () {
    $export = ExportPresenter::for(krkToLgw());

    expect($export->field('flight')->display)->toBe('easyJet UK U2 8824')
        ->and($export->field('distance')->display)->toBe('876 miles')
        ->and($export->field('distance')->raw)->toBe(1409785)
        ->and($export->field('duration')->display)->toBe('2h 27m')
        ->and($export->field('duration')->raw)->toBe(8820)
        ->and($export->field('cabin')->display)->toBe('Economy')
        ->and($export->field('cabin')->raw)->toBe('economy')
        ->and($export->field('origin')->raw)->toMatchArray(['iata' => 'KRK', 'icao' => 'EPKK']);
});

it('never leaks an id, a timestamp or a password', function () {
    $json = json_encode(ExportPresenter::for(krkToLgw())->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});

it('links a flight to its airline, its archive and its day, absolutely', function () {
    $export = ExportPresenter::for(krkToLgw());
    $keys = array_map(fn ($l) => $l->key, $export->links);

    expect($keys)->toContain('airline', 'type', 'day')
        ->and($export->links[0]->url)->toStartWith(config('app.url'));
});
