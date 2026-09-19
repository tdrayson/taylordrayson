<?php

use App\Models\Food;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a food day as its aggregated totals, not one row', function () {
    Food::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00', 'name' => 'Porridge', 'meal' => 'breakfast',
        'calories' => 220, 'protein' => 8, 'carbs' => 35, 'fat' => 5,
        'saturated_fat' => 1.5, 'sugars' => 6, 'fibre' => 4, 'sodium' => 120,
        'status' => 'published',
    ]);
    $lunch = Food::factory()->create([
        'occurred_at' => '2026-09-13 13:00:00', 'name' => 'Salad', 'meal' => 'lunch',
        'calories' => 250, 'protein' => 15, 'carbs' => 20, 'fat' => 10,
        'saturated_fat' => 2, 'sugars' => 5, 'fibre' => 6, 'sodium' => 300,
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($lunch);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['calories', 'protein', 'carbs', 'fat', 'saturated_fat', 'sugars', 'fibre', 'sodium', 'meals'])
        ->and($export->field('calories')->display)->toBe('470 kcal')
        ->and($export->field('calories')->raw)->toBe(470)
        ->and($export->field('protein')->display)->toBe('23g')
        ->and($export->field('carbs')->display)->toBe('55g')
        ->and($export->field('fat')->display)->toBe('15g')
        ->and($export->field('saturated_fat')->display)->toBe('3.5g')
        ->and($export->field('sugars')->display)->toBe('11g')
        ->and($export->field('fibre')->display)->toBe('10g')
        ->and($export->field('sodium')->display)->toBe('420mg')
        ->and($export->field('meals')->display)->toBe('Breakfast (220 kcal), Lunch (250 kcal)');
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
