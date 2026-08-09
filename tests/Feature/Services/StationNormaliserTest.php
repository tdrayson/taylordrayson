<?php

use App\Services\PetrolPrices\StationNormaliser;

it('title-cases station names while preserving fuel acronyms', function () {
    expect(StationNormaliser::name('GODSTONE ROAD SF CONNECT'))->toBe('Godstone Road SF Connect');
    expect(StationNormaliser::name('MFG HAYLING DOWN'))->toBe('MFG Hayling Down');
    expect(StationNormaliser::name('CLACKET LANE EAST CONNECT MWSA'))->toBe('Clacket Lane East Connect MWSA');
    expect(StationNormaliser::name('SHELL COBHAM'))->toBe('Shell Cobham');
});

it('handles hyphens, apostrophes, and already-clean names', function () {
    expect(StationNormaliser::name('BY-PASS SF CONNECT'))->toBe('By-Pass SF Connect');
    expect(StationNormaliser::name('SAINSBURYS PURLEY WAY'))->toBe('Sainsburys Purley Way');
    expect(StationNormaliser::name('Beddington Lane Service Station'))->toBe('Beddington Lane Service Station');
});

it('title-cases cities', function () {
    expect(StationNormaliser::city('SOUTH CROYDON'))->toBe('South Croydon');
    expect(StationNormaliser::city('LONDON GATWICK AIRPORT'))->toBe('London Gatwick Airport');
    expect(StationNormaliser::city(null))->toBeNull();
});

it('takes the forecourt name from the trailing parenthetical', function (?string $label, ?string $expected) {
    expect(StationNormaliser::tradingName($label))->toBe($expected);
})->with([
    // "BRAND TOWN (TRADING NAME)": the parenthetical is the forecourt's own name.
    ['BP WHYTELEAFE (GODSTONE ROAD SF CONNECT)', 'Godstone Road SF Connect'],
    ['ESSO BRIGHTON ROAD (MFG HAYLING DOWN)', 'MFG Hayling Down'],
    ['ESSO OXTED (RSS OLD OXTED)', 'RSS Old Oxted'],
    // Some repeat the generated label, which is still the best name available.
    ['SHELL WHYTELEAFE (SHELL WHYTELEAFE)', 'Shell Whyteleafe'],
    // No parenthetical, so the whole label stands.
    ['ASDA EXPRESS BIGGIN HILL', 'Asda Express Biggin Hill'],
    [null, null],
    ['', null],
]);

it('title-cases addresses but keeps postcode fragments uppercase', function () {
    $address = StationNormaliser::address('PURLEY WAY SERVICE STATION LTD, 514, PURLEY WAY, CROYDON, CR0 4RE, CROYDON');

    expect($address)->toContain('Purley Way Service Station Ltd');
    expect($address)->toContain('CR0 4RE');
    expect($address)->not->toContain('Cr0');
});
