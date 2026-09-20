<?php

use App\Enums\ExportFormat;
use App\Models\Food;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\SerialNumber;

it('prints a food day as a supermarket receipt', function () {
    $breakfast = Food::factory()->create([
        'occurred_at' => '2026-09-19 08:00:00', 'name' => 'Porridge', 'meal' => 'breakfast',
        'quantity' => 1, 'units' => 'Serving',
        'calories' => 180, 'protein' => 8, 'carbs' => 35, 'fat' => 5,
        'saturated_fat' => 1.5, 'sugars' => 6, 'fibre' => 4, 'sodium' => 120,
        'status' => 'published',
    ]);
    Food::factory()->create([
        'occurred_at' => '2026-09-19 08:00:00', 'name' => 'Curry', 'meal' => 'dinner',
        'quantity' => 1, 'units' => 'Serving',
        'calories' => 650, 'protein' => 28, 'carbs' => 55, 'fat' => 20,
        'saturated_fat' => 6, 'sugars' => 8, 'fibre' => 5, 'sodium' => 900,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($breakfast);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toStartWith('=')
        ->and($txt)->toContain(mb_strtoupper(config('identity.name')))
        ->and($txt)->toContain('19 SEP 2026')
        ->and($txt)->toContain('BREAKFAST')
        ->and($txt)->toContain('1 Porridge')
        ->and($txt)->toContain('180 kcal')
        ->and($txt)->toContain('DINNER')
        ->and($txt)->toContain('1 Curry')
        ->and($txt)->toContain('650 kcal')
        ->and($txt)->toContain('PROTEIN')
        ->and($txt)->toContain('CARBOHYDRATE')
        ->and($txt)->toContain('FAT')
        ->and($txt)->toContain('TOTAL')
        ->and($txt)->toContain('830 kcal')
        ->and($txt)->toContain('ITEMS LOGGED: 2')
        ->and($txt)->toContain('THANK YOU FOR EATING!')
        ->and($txt)->toContain('No. '.SerialNumber::for($breakfast->occurred_at, $breakfast->id));

    foreach (explode("\n", $txt) as $line) {
        expect(mb_strwidth($line))->toBeLessThanOrEqual(46);
    }
});

it('drops a unit that says nothing, "serving", from an item line', function () {
    $food = Food::factory()->create([
        'occurred_at' => '2026-09-19 08:00:00', 'name' => 'Porridge', 'meal' => 'breakfast',
        'quantity' => 1, 'units' => 'Serving', 'calories' => 180,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($food);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('1 Porridge')
        ->and($txt)->not->toContain('Serving');
});

it('keeps a meaningful unit on an item line', function () {
    $food = Food::factory()->create([
        'occurred_at' => '2026-09-19 08:00:00', 'name' => 'Gummy Candy', 'meal' => 'lunch',
        'quantity' => 75, 'units' => 'g', 'calories' => 339,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($food);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('75 g Gummy Candy');
});

it('renders the same barcode for the same day every time', function () {
    $food = Food::factory()->create(['occurred_at' => '2026-09-19 08:00:00', 'status' => 'published']);

    $data = ExportPresenter::for($food);
    $first = Formats::find($data, ExportFormat::Txt)->render($data);
    $second = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($first)->toBe($second);
});
