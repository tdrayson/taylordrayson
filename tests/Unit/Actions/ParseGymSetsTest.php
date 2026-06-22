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
        ->and($sets[0])->toBe(['exercise' => 'Bench Press', 'reps' => 8, 'weight' => 29.0])
        ->and($sets[3])->toBe(['exercise' => 'Bench Press', 'reps' => 8, 'weight' => 49.0])
        ->and($sets[4])->toBe(['exercise' => 'chest press', 'reps' => 8, 'weight' => 20.0])
        ->and($sets[5])->toBe(['exercise' => 'chest press', 'reps' => 12, 'weight' => 25.0])
        ->and($sets[7])->toBe(['exercise' => 'chest press', 'reps' => 8, 'weight' => 20.0])
        ->and($sets[8])->toBe(['exercise' => 'Seated Shoulder Press', 'reps' => 8, 'weight' => 20.0])
        ->and($sets[9])->toBe(['exercise' => 'Seated Shoulder Press', 'reps' => 8, 'weight' => 20.0])
        ->and($sets[10])->toBe(['exercise' => 'Seated Shoulder Press', 'reps' => 8, 'weight' => 20.0])
        ->and($sets[11])->toBe(['exercise' => 'Rope Tricep extension', 'reps' => 12, 'weight' => 12.5])
        ->and($sets[13])->toBe(['exercise' => 'Rope Tricep extension', 'reps' => 12, 'weight' => 15.0]);
});

it('ignores blank lines', function () {
    $sets = (new ParseGymSets)("Squat • 5 rep 60 kg\n\nDeadlift • 5 rep 80 kg");

    expect($sets)->toHaveCount(2);
});
