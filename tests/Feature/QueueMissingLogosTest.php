<?php

use App\Actions\Flights\CreateFlight;
use App\Actions\Flights\UpdateFlight;
use App\Actions\Fuel\CreateFuel;
use App\Actions\Fuel\UpdateFuel;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Fuel;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

afterEach(function () {
    File::delete(public_path('logos/brands/texaco.png'));
    File::delete(public_path('logos/airlines/icon/ZZ.png'));
    File::delete(public_path('logos/airlines/logo/ZZ.png'));
});

function putLogo(string $relative): void
{
    File::ensureDirectoryExists(dirname(public_path($relative)));
    File::put(public_path($relative), 'existing');
}

function flightPayload(): array
{
    Airport::factory()->create(['iata_code' => 'LGW']);
    Airport::factory()->create(['iata_code' => 'MAD']);

    return [
        'occurred_at' => '2026-08-12 10:35:00',
        'flight_number' => '2718',
        'airline_icao' => 'ZZZ',
        'origin_iata' => 'LGW',
        'destination_iata' => 'MAD',
    ];
}

it('queues the logo fetch for a new fuel brand with no logo', function () {
    app(CreateFuel::class)(['litres' => 10.0, 'cost' => 15.0, 'brand' => 'Texaco']);

    Queue::assertPushed(QueuedCommand::class);
});

it('queues nothing when the brand logo is already on disk', function () {
    putLogo('logos/brands/texaco.png');

    app(CreateFuel::class)(['litres' => 10.0, 'cost' => 15.0, 'brand' => 'Texaco']);

    Queue::assertNotPushed(QueuedCommand::class);
});

it('queues the logo fetch when an update gives a fill-up a brand with no logo', function () {
    $fuel = Fuel::factory()->create(['brand' => null]);

    app(UpdateFuel::class)($fuel, ['brand' => 'Texaco']);

    Queue::assertPushed(QueuedCommand::class);
});

it('queues the logo fetch for a new flight whose airline has no logo', function () {
    Airline::factory()->create(['icao_code' => 'ZZZ', 'iata_code' => 'ZZ']);

    app(CreateFlight::class)(flightPayload());

    Queue::assertPushed(QueuedCommand::class);
});

it('queues nothing when the airline icon and logo are already on disk', function () {
    Airline::factory()->create(['icao_code' => 'ZZZ', 'iata_code' => 'ZZ']);
    putLogo('logos/airlines/icon/ZZ.png');
    putLogo('logos/airlines/logo/ZZ.png');

    app(CreateFlight::class)(flightPayload());

    Queue::assertNotPushed(QueuedCommand::class);
});

it('queues the logo fetch when an update moves a flight to an airline with no logo', function () {
    Airline::factory()->create(['icao_code' => 'ZZZ', 'iata_code' => 'ZZ']);
    Airline::factory()->create(['icao_code' => 'YYY', 'iata_code' => 'YY']);
    putLogo('logos/airlines/icon/ZZ.png');
    putLogo('logos/airlines/logo/ZZ.png');
    $flight = Flight::factory()->create(['airline_icao' => 'ZZZ']);
    Queue::assertNothingPushed();

    app(UpdateFlight::class)($flight, ['airline_icao' => 'YYY']);

    Queue::assertPushed(QueuedCommand::class);
});
