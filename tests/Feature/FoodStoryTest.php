<?php

use App\Models\Calorie;
use App\Stories\FoodStory;

it('reports no data when there are no logs', function () {
    expect(app(FoodStory::class)->build())->toBe(['hasData' => false]);
});

it('rolls per-item logs into daily and headline figures', function () {
    // Day one: two items totalling 1,500 kcal. Day two: one item, 500 kcal.
    Calorie::factory()->create(['occurred_at' => '2024-01-01 00:00:00', 'calories' => 600, 'protein' => 20, 'meal' => 'breakfast']);
    Calorie::factory()->create(['occurred_at' => '2024-01-01 00:00:00', 'calories' => 900, 'protein' => 40, 'meal' => 'dinner']);
    Calorie::factory()->create(['occurred_at' => '2024-01-02 00:00:00', 'calories' => 500, 'protein' => 10, 'meal' => 'lunch']);

    $story = app(FoodStory::class)->build();

    expect($story['hasData'])->toBeTrue()
        ->and($story['kpis']['days'])->toBe(2)
        ->and($story['kpis']['items'])->toBe(3)
        ->and($story['kpis']['totalKcal'])->toBe(2000)
        ->and($story['kpis']['avgPerDay'])->toBe(1000)
        ->and($story['distribution']['high']['kcal'])->toBe(1500)
        ->and($story['distribution']['high']['when'])->toBe('1 January 2024')
        ->and($story['distribution']['low']['kcal'])->toBe(500)
        ->and($story['streak']['days'])->toBe(2);
});

it('counts fizzy drinks per year, across brands, for the fizzy chapter', function () {
    Calorie::factory()->count(3)->create(['occurred_at' => '2024-06-01 00:00:00', 'name' => 'Can Of Coke', 'calories' => 139]);
    Calorie::factory()->create(['occurred_at' => '2024-06-02 00:00:00', 'name' => 'Pepsi Max', 'calories' => 1]);
    Calorie::factory()->create(['occurred_at' => '2024-06-03 00:00:00', 'name' => 'Fanta Orange', 'calories' => 50]);
    // Not fizzy, and "chocolate" must not match the "cola" pattern.
    Calorie::factory()->create(['occurred_at' => '2024-06-04 00:00:00', 'name' => 'Chocolate Bar', 'calories' => 250]);
    Calorie::factory()->create(['occurred_at' => '2024-06-05 00:00:00', 'name' => 'Orange Juice', 'calories' => 90]);

    $fizzy = app(FoodStory::class)->build()['fizzy'];

    expect($fizzy['total'])->toBe(5)
        ->and($fizzy['peak'])->toBe(['year' => 2024, 'count' => 5]);
});
