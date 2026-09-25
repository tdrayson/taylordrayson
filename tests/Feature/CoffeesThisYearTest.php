<?php

use App\Models\Food;
use App\Queries\CoffeesThisYear;
use Illuminate\Support\Carbon;

it('counts every coffee drink logged this calendar year, and nothing that only names one', function () {
    Carbon::setTestNow('2026-09-25 09:00:00');

    foreach (['Flat White', 'Oat LATTE', 'Americano, black', 'Iced coffee'] as $name) {
        Food::factory()->create(['name' => $name, 'quantity' => 1, 'units' => 'Serving', 'occurred_at' => '2026-03-01 12:00:00']);
    }

    foreach (['Chocolate brownie', 'BBQ Americano Pizza (Pan)', 'Espresso Martini'] as $name) {
        Food::factory()->create(['name' => $name, 'quantity' => 1, 'units' => 'Serving', 'occurred_at' => '2026-03-01 12:00:00']);
    }

    Food::factory()->create(['name' => 'Cappuccino', 'quantity' => 1, 'units' => 'Serving', 'occurred_at' => '2025-12-31 12:00:00']);

    expect(app(CoffeesThisYear::class)())->toBe(4);
});

it('recounts once a new coffee is logged', function () {
    Carbon::setTestNow('2026-09-25 09:00:00');

    Food::factory()->create(['name' => 'Cortado', 'quantity' => 1, 'units' => 'Serving', 'occurred_at' => '2026-09-24 12:00:00']);
    expect(app(CoffeesThisYear::class)())->toBe(1);

    Food::factory()->create(['name' => 'Mocha', 'quantity' => 1, 'units' => 'Serving', 'occurred_at' => '2026-09-25 12:00:00']);
    expect(app(CoffeesThisYear::class)())->toBe(2);
});

it('counts a multi-serving row as that many coffees, and a measured one as one', function () {
    Carbon::setTestNow('2026-09-25 09:00:00');

    Food::factory()->create(['name' => 'Flat White', 'quantity' => 2, 'units' => 'Servings', 'occurred_at' => '2026-03-01 12:00:00']);
    Food::factory()->create(['name' => 'Oat Latte', 'quantity' => 180, 'units' => 'Milliliters', 'occurred_at' => '2026-03-01 12:00:00']);
    Food::factory()->create(['name' => 'Caramel Frappuccino 250ml Glass Bottle', 'quantity' => 1, 'units' => 'Serving', 'occurred_at' => '2026-03-01 12:00:00']);

    expect(app(CoffeesThisYear::class)())->toBe(4);
});
