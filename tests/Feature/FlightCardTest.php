<?php

use App\Models\Airport;
use App\Models\Flight;
use App\Presenters\CardPresenter;

it('titles a flight by its codes and spells the airports out for screen readers', function () {
    Airport::factory()->create(['iata_code' => 'KRK', 'name' => 'Kraków John Paul II International Airport', 'city' => 'Balice', 'country' => 'PL']);
    Airport::factory()->create(['iata_code' => 'LGW', 'name' => 'London Gatwick Airport', 'city' => 'London', 'country' => 'GB']);

    $flight = Flight::factory()->create(['origin_iata' => 'KRK', 'destination_iata' => 'LGW'])->load('origin', 'destination');

    $card = CardPresenter::for($flight);

    expect($card->title)->toBe('KRK → LGW')
        ->and($card->titleLabel)->toBe('Kraków John Paul II International Airport to London Gatwick Airport');
});

it('falls back to the code for each airport it cannot name', function () {
    Airport::factory()->create(['iata_code' => 'KRK', 'name' => 'Kraków John Paul II International Airport']);
    Airport::factory()->create(['iata_code' => 'LGW', 'name' => null, 'city' => 'London']);

    $loaded = Flight::factory()->create(['origin_iata' => 'KRK', 'destination_iata' => 'LGW'])->load('origin', 'destination');
    $unloaded = Flight::factory()->create(['origin_iata' => 'KRK', 'destination_iata' => 'LGW']);

    expect(CardPresenter::for($loaded)->titleLabel)->toBe('Kraków John Paul II International Airport to LGW')
        ->and(CardPresenter::for($unloaded)->title)->toBe('KRK → LGW')
        ->and(CardPresenter::for($unloaded)->titleLabel)->toBe('KRK to LGW');
});
