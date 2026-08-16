<?php

use App\Actions\Fuel\DeriveFuelFigures;

it('works the litres out from the cost and the price per litre', function () {
    $figures = (new DeriveFuelFigures)(['cost' => 48.63, 'price_per_litre' => 1.539]);

    expect($figures['litres'])->toBe(31.598);
});

it('still works the price out from a fill-up entered as litres, as an import does', function () {
    $figures = (new DeriveFuelFigures)(['cost' => 48.63, 'litres' => 31.598]);

    expect($figures['price_per_litre'])->toBe(1.539);
});

it('derives only one figure, so the three always multiply back to the cost', function () {
    $row = ['litres' => 31.598, 'cost' => 48.63, 'price_per_litre' => 1.539];

    // Correcting the cost re-derives the litres and leaves the price alone.
    // Deriving both would use each other's stale value and contradict itself.
    $figures = (new DeriveFuelFigures)(['cost' => 60.00], $row);

    expect($figures['litres'])->toBe(38.986)
        ->and($figures)->not->toHaveKey('price_per_litre')
        ->and(round($figures['litres'] * $row['price_per_litre'], 2))->toBe(60.0);
});

it('leaves the figures untouched when an edit touches none of them', function () {
    $row = ['litres' => 31.598, 'cost' => 48.63, 'price_per_litre' => 1.539];

    expect((new DeriveFuelFigures)(['odometer' => 50000], $row))->toBe(['odometer' => 50000]);
});
