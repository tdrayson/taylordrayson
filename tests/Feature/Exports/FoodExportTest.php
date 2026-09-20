<?php

use App\Data\Aspects\MealBreakdown;
use App\Models\Food;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\SerialNumber;

it('publishes a food day as its aggregated totals, not one row', function () {
    Food::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00', 'name' => 'Porridge', 'meal' => 'breakfast',
        'quantity' => 1, 'units' => 'Serving',
        'calories' => 220, 'protein' => 8, 'carbs' => 35, 'fat' => 5,
        'saturated_fat' => 1.5, 'sugars' => 6, 'fibre' => 4, 'sodium' => 120,
        'status' => 'published',
    ]);
    $lunch = Food::factory()->create([
        'occurred_at' => '2026-09-13 13:00:00', 'name' => 'Salad', 'meal' => 'lunch',
        'quantity' => 1, 'units' => 'Serving',
        'calories' => 250, 'protein' => 15, 'carbs' => 20, 'fat' => 10,
        'saturated_fat' => 2, 'sugars' => 5, 'fibre' => 6, 'sodium' => 300,
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($lunch);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['calories', 'protein', 'carbs', 'fat', 'saturated_fat', 'sugars', 'fibre', 'sodium', 'meals', 'items_logged', 'owner', 'receipt_date', 'receipt_number'])
        ->and($export->field('calories')->display)->toBe('470 kcal')
        ->and($export->field('calories')->raw)->toBe(470)
        ->and($export->field('protein')->display)->toBe('23g')
        ->and($export->field('carbs')->display)->toBe('55g')
        ->and($export->field('fat')->display)->toBe('15g')
        ->and($export->field('saturated_fat')->display)->toBe('3.5g')
        ->and($export->field('sugars')->display)->toBe('11g')
        ->and($export->field('fibre')->display)->toBe('10g')
        ->and($export->field('sodium')->display)->toBe('420mg')
        ->and($export->field('meals')->display)->toBe('Breakfast (220 kcal), Lunch (250 kcal)')
        ->and($export->field('items_logged')->display)->toBe('2')
        ->and($export->field('owner')->display)->toBe(config('identity.name'))
        ->and($export->field('receipt_date')->display)->toBe('13 Sep 2026')
        ->and($export->field('receipt_number')->display)->toBe(SerialNumber::for($lunch->occurred_at));
});

it('publishes a MealBreakdown aspect with every item already formatted', function () {
    Food::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00', 'name' => 'Porridge', 'meal' => 'breakfast',
        'quantity' => 1, 'units' => 'Serving', 'calories' => 220,
        'status' => 'published',
    ]);
    $food = Food::factory()->create([
        'occurred_at' => '2026-09-13 13:00:00', 'name' => 'Gummy Candy', 'meal' => 'lunch',
        'quantity' => 75, 'units' => 'g', 'calories' => 339,
        'status' => 'published',
    ]);

    $breakdown = ExportPresenter::for($food)->aspect(MealBreakdown::class);

    expect($breakdown)->toBeInstanceOf(MealBreakdown::class)
        ->and($breakdown->meals)->toHaveCount(2)
        ->and($breakdown->meals[0]->label)->toBe('Breakfast')
        ->and($breakdown->meals[0]->items[0]->name)->toBe('1 Porridge')
        ->and($breakdown->meals[0]->items[0]->calories)->toBe('220 kcal')
        ->and($breakdown->meals[1]->label)->toBe('Lunch')
        ->and($breakdown->meals[1]->items[0]->name)->toBe('75 g Gummy Candy')
        ->and($breakdown->meals[1]->items[0]->calories)->toBe('339 kcal');
});

it('renders an absurdly large macro as a plain number rather than scientific notation', function () {
    $food = Food::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00', 'name' => 'Junk data', 'meal' => 'breakfast',
        'calories' => 220, 'protein' => 100000000000000, 'carbs' => 35, 'fat' => 5,
        'saturated_fat' => 1.5, 'sugars' => 6, 'fibre' => 4, 'sodium' => 100000000000000,
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($food);

    expect($export->field('protein')->display)->toBe('100,000,000,000,000g')
        ->and($export->field('protein')->display)->not->toContain('E+')
        ->and($export->field('sodium')->display)->toBe('100,000,000,000,000mg')
        ->and($export->field('sodium')->display)->not->toContain('E+');
});

it('offers neither geojson nor ics for a food day', function () {
    $food = Food::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($food)));

    expect($formats)->not->toContain('geojson')
        ->and($formats)->not->toContain('ics');
});

it('never leaks an id, a timestamp or a password', function () {
    $food = Food::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($food)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
