<?php

use App\Presenters\ExportPresenter;

it('publishes a flight as labelled fields in order', function () {
    $export = ExportPresenter::for(krkToLgw());

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe([
            'flight', 'flight_code', 'airline', 'origin', 'origin_code', 'origin_city',
            'destination', 'destination_code', 'destination_city', 'departed', 'arrived',
            'departs_time', 'arrives_time', 'date', 'duration', 'distance', 'cabin', 'reason',
            'passenger', 'ticket_ref',
        ]);
});

it('formats each flight field for a reader and keeps the machine value in raw', function () {
    $flight = krkToLgw();
    $export = ExportPresenter::for($flight);

    expect($export->field('flight')->display)->toBe('easyJet UK U2 8824')
        ->and($export->field('flight_code')->display)->toBe('U2 8824')
        ->and($export->field('airline')->display)->toBe('easyJet UK')
        ->and($export->field('distance')->display)->toBe('876 miles')
        ->and($export->field('distance')->raw)->toBe(1409785)
        ->and($export->field('duration')->display)->toBe('2h 27m')
        ->and($export->field('duration')->raw)->toBe(8820)
        ->and($export->field('cabin')->display)->toBe('Economy')
        ->and($export->field('cabin')->raw)->toBe('economy')
        ->and($export->field('origin')->raw)->toMatchArray(['iata' => 'KRK', 'icao' => 'EPKK'])
        ->and($export->field('origin_code')->display)->toBe('KRK')
        ->and($export->field('origin_city')->display)->toBe('Kraków')
        ->and($export->field('destination_code')->display)->toBe('LGW')
        ->and($export->field('destination_city')->display)->toBe('London')
        ->and($export->field('departs_time')->display)->toBe('22:15')
        ->and($export->field('arrives_time')->display)->toBe('23:42')
        ->and($export->field('passenger')->display)->toBe('DRAYSON / TAYLOR')
        ->and($export->field('ticket_ref')->raw)->toBe($flight->id);
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
