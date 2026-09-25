<?php

use App\Models\Food;
use App\Queries\CoffeesThisYear;
use Illuminate\Support\Carbon;

it('counts every coffee drink logged this calendar year, and nothing that only names one', function () {
    Carbon::setTestNow('2026-09-25 09:00:00');

    foreach (['Flat White', 'Oat LATTE', 'Americano, black', 'Iced coffee'] as $name) {
        Food::factory()->create(['name' => $name, 'occurred_at' => '2026-03-01 12:00:00']);
    }

    foreach (['Chocolate brownie', 'BBQ Americano Pizza (Pan)', 'Espresso Martini'] as $name) {
        Food::factory()->create(['name' => $name, 'occurred_at' => '2026-03-01 12:00:00']);
    }

    Food::factory()->create(['name' => 'Cappuccino', 'occurred_at' => '2025-12-31 12:00:00']);

    expect(app(CoffeesThisYear::class)())->toBe(4);
});

it('recounts once a new coffee is logged', function () {
    Carbon::setTestNow('2026-09-25 09:00:00');

    Food::factory()->create(['name' => 'Cortado', 'occurred_at' => '2026-09-24 12:00:00']);
    expect(app(CoffeesThisYear::class)())->toBe(1);

    Food::factory()->create(['name' => 'Mocha', 'occurred_at' => '2026-09-25 12:00:00']);
    expect(app(CoffeesThisYear::class)())->toBe(2);
});
