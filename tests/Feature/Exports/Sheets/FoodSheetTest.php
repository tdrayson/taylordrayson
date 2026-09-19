<?php

use App\Enums\ExportFormat;
use App\Models\Food;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a food day as a boxed nutrition panel', function () {
    $lunch = Food::factory()->create([
        'occurred_at' => '2026-09-13 13:00:00', 'name' => 'Salad', 'meal' => 'lunch',
        'calories' => 250, 'protein' => 15, 'carbs' => 20, 'fat' => 10,
        'saturated_fat' => 2, 'sugars' => 5, 'fibre' => 6, 'sodium' => 300,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($lunch);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('NUTRITION FACTS')
        ->and($txt)->toContain('250 kcal')
        ->and($txt)->toContain('Protein')
        ->and($txt)->toContain('15g')
        ->and($txt)->toContain('300mg')
        // 6.0 is the raw fibre grams; the sheet must print "6g".
        ->and($txt)->not->toContain('6.0g');
});
