<?php

use App\Models\Fuel;

use function Pest\Laravel\get;

it('computes range and mpg from the next fill (leading method)', function () {
    $fill = Fuel::factory()->create([
        'vehicle_id' => 'hn14wxp',
        'odometer' => 20000,
        'litres' => 40,
        'occurred_at' => '2026-05-01 10:00:00',
    ]);
    // Next fill 400 miles later; 40 L = 8.799 imperial gallons => 45.5 mpg.
    Fuel::factory()->create([
        'vehicle_id' => 'hn14wxp',
        'odometer' => 20400,
        'occurred_at' => '2026-05-10 10:00:00',
    ]);

    get($fill->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entry.miles_this_tank', 400)
            ->where('entry.mpg', fn ($value) => round((float) $value, 1) === 45.5)
        );
});

it('leaves range and mpg null on the latest fill', function () {
    Fuel::factory()->create([
        'vehicle_id' => 'hn14wxp',
        'odometer' => 20000,
        'occurred_at' => '2026-05-01 10:00:00',
    ]);
    $latest = Fuel::factory()->create([
        'vehicle_id' => 'hn14wxp',
        'odometer' => 20400,
        'litres' => 40,
        'occurred_at' => '2026-05-10 10:00:00',
    ]);

    get($latest->url())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entry.miles_this_tank', null)
            ->where('entry.mpg', null)
        );
});
