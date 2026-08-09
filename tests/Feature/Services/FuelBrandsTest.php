<?php

use App\Services\PetrolPrices\FuelBrands;

it('canonicalises the display name for a known brand', function (string $raw, string $expected) {
    expect(FuelBrands::name($raw))->toBe($expected);
})->with([
    ['SHELL', 'Shell'],
    ['Texaco', 'Texaco'],
    ['BP', 'BP'],
    ['TESCO', 'Tesco'],
    ["SAINSBURY'S", "Sainsbury's"],
    ['MOTOR FUEL GROUP', 'MFG'],
]);

it('title-cases an unlisted brand and gives it no domain', function () {
    expect(FuelBrands::name('INDIE FUELS'))->toBe('Indie Fuels');
    expect(FuelBrands::domain('INDIE FUELS'))->toBeNull();
});

it('resolves the logo domain regardless of the casing the feed uses', function () {
    expect(FuelBrands::domain('esso'))->toBe('esso.co.uk');
    expect(FuelBrands::domain('ESSO'))->toBe('esso.co.uk');
    expect(FuelBrands::domain(' Esso '))->toBe('esso.co.uk');
});
