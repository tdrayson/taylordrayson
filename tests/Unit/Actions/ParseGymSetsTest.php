<?php

use App\Actions\ParseGymSets;

it('expands gym log lines into flat set entries', function () {
    $text = <<<'TEXT'
Bench Press • 8 rep: 29, 39, 44, 49 kg
chest press • 8 rep 20 kg, 12 rep 25 kg, 12 rep 25 kg, 8 rep 20 kg
Seated Shoulder Press • 3 sets: 8 rep 20 kg
Rope Tricep extension • 12 rep: 12.5, 12.5, 15 kg
TEXT;

    $sets = (new ParseGymSets)($text);

    expect($sets)->toHaveCount(14)
        ->and($sets[0])->toBe(['exercise' => 'Bench Press', 'reps' => 8, 'weight_kg' => 29.0])
        ->and($sets[3])->toBe(['exercise' => 'Bench Press', 'reps' => 8, 'weight_kg' => 49.0])
        ->and($sets[4])->toBe(['exercise' => 'chest press', 'reps' => 8, 'weight_kg' => 20.0])
        ->and($sets[5])->toBe(['exercise' => 'chest press', 'reps' => 12, 'weight_kg' => 25.0])
        ->and($sets[7])->toBe(['exercise' => 'chest press', 'reps' => 8, 'weight_kg' => 20.0])
        ->and($sets[8])->toBe(['exercise' => 'Seated Shoulder Press', 'reps' => 8, 'weight_kg' => 20.0])
        ->and($sets[9])->toBe(['exercise' => 'Seated Shoulder Press', 'reps' => 8, 'weight_kg' => 20.0])
        ->and($sets[10])->toBe(['exercise' => 'Seated Shoulder Press', 'reps' => 8, 'weight_kg' => 20.0])
        ->and($sets[11])->toBe(['exercise' => 'Rope Tricep extension', 'reps' => 12, 'weight_kg' => 12.5])
        ->and($sets[13])->toBe(['exercise' => 'Rope Tricep extension', 'reps' => 12, 'weight_kg' => 15.0]);
});

it('ignores blank lines', function () {
    $sets = (new ParseGymSets)("Squat • 5 rep 60 kg\n\nDeadlift • 5 rep 80 kg");

    expect($sets)->toHaveCount(2);
});

it('keeps bodyweight sets, scoring them at zero kg', function () {
    $sets = (new ParseGymSets)("Trx push up • 3 sets: 12 rep\nPull up • 8 rep");

    expect($sets)->toHaveCount(4)
        ->and($sets[0])->toBe(['exercise' => 'Trx push up', 'reps' => 12, 'weight_kg' => 0.0])
        ->and($sets[3])->toBe(['exercise' => 'Pull up', 'reps' => 8, 'weight_kg' => 0.0]);
});

it('reads a whole Setgraph share, summary and credit lines included', function () {
    $text = <<<'TEXT'
Lat Pulldown • 12 rep: 32, 36, 36 kg
Dumbbell Bench Press • 12 rep: 12, 16, 16 kg
Trx push up • 3 sets: 12 rep
chest press • 3 sets: 12 rep 10 kg

Other • 38 min

Tracked on Setgraph
TEXT;

    $sets = (new ParseGymSets)($text);

    // 12 sets across 4 exercises: the summary and credit lines contribute none.
    expect($sets)->toHaveCount(12)
        ->and(array_unique(array_column($sets, 'exercise')))->toHaveCount(4);
});

it('reads a fixed weight with varying reps, the mirror of a rep count with varying weights', function () {
    $sets = (new ParseGymSets)('Lat Pulldown • 32 kg: 12, 12, 10 rep');

    expect($sets)->toHaveCount(3)
        ->and($sets[0])->toBe(['exercise' => 'Lat Pulldown', 'reps' => 12, 'weight_kg' => 32.0])
        ->and($sets[2])->toBe(['exercise' => 'Lat Pulldown', 'reps' => 10, 'weight_kg' => 32.0]);
});

it('reads a share that states no length', function () {
    $text = <<<'TEXT'
Chest Supported Incline Dumbbell Row • 3 sets: 10 rep 12 kg
Lat Pulldown • 32 kg: 12, 12, 10 rep
Ring Row • 3 sets: 15 rep
TRX Bicep Curl • 3 sets: 15 rep

Tracked on Setgraph
TEXT;

    $sets = (new ParseGymSets)($text);

    expect($sets)->toHaveCount(12)
        ->and(array_unique(array_column($sets, 'exercise')))->toHaveCount(4);
});
