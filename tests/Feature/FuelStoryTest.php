<?php

use App\Models\Fuel;
use App\Stories\FuelStory;

it('reports no data when there are no fills', function () {
    expect(app(FuelStory::class)->build())->toBe(['hasData' => false]);
});

it('computes kpis, price swing and mpg from fills', function () {
    // Three fills; the odometer deltas give two 300-mile legs on 40 litres each.
    Fuel::factory()->create(['occurred_at' => '2020-11-01 09:00:00', 'odometer' => 10000, 'litres' => 40, 'price_per_litre' => 1.07, 'cost' => 42.80]);
    Fuel::factory()->create(['occurred_at' => '2022-06-01 09:00:00', 'odometer' => 10300, 'litres' => 40, 'price_per_litre' => 1.899, 'cost' => 75.96]);
    Fuel::factory()->create(['occurred_at' => '2022-07-01 09:00:00', 'odometer' => 10600, 'litres' => 40, 'price_per_litre' => 1.50, 'cost' => 60.00]);

    $story = app(FuelStory::class)->build();

    expect($story['hasData'])->toBeTrue()
        ->and($story['kpis']['fills'])->toBe(3)
        ->and($story['kpis']['miles'])->toBe(600)
        ->and($story['price']['low']['value'])->toBe(1.07)
        ->and($story['price']['low']['when'])->toBe('November 2020')
        ->and($story['price']['high']['value'])->toBe(1.899)
        ->and($story['price']['swingPct'])->toBe(77)
        // 300 miles on 40 L = 300 / (40 / 4.546) = 34.1 mpg per leg.
        ->and($story['kpis']['avgMpg'])->toBe(34.1);
});

it('builds a running cumulative of the fuel-card saving', function () {
    Fuel::factory()->create(['occurred_at' => '2024-01-10 09:00:00', 'cost' => 50.00, 'fuel_card_cost' => 47.00]);
    Fuel::factory()->create(['occurred_at' => '2024-02-10 09:00:00', 'cost' => 60.00, 'fuel_card_cost' => 55.00]);

    $card = app(FuelStory::class)->build()['fuelCard'];

    expect($card['fills'])->toBe(2)
        ->and($card['saved'])->toBe(8.0)
        ->and($card['cumulative'])->toBe([
            ['date' => '2024-01-10', 'saved' => 3.0],
            ['date' => '2024-02-10', 'saved' => 8.0],
        ]);
});

it('flags the longest gaps between fills', function () {
    Fuel::factory()->create(['occurred_at' => '2020-03-01 09:00:00', 'odometer' => 10000]);
    Fuel::factory()->create(['occurred_at' => '2020-07-03 09:00:00', 'odometer' => 10500]); // 124-day gap
    Fuel::factory()->create(['occurred_at' => '2020-07-20 09:00:00', 'odometer' => 10800]); // 17-day gap

    $story = app(FuelStory::class)->build();

    expect($story['gaps'][0]['days'])->toBe(124)
        ->and($story['gaps'][0]['from'])->toBe('1 March 2020')
        ->and($story['gaps'][0]['to'])->toBe('3 July 2020')
        // Intervals between the three fills are 124 and 17 days.
        ->and($story['intervals'])->toBe(['longest' => 124, 'shortest' => 17, 'typical' => 70]);
});
