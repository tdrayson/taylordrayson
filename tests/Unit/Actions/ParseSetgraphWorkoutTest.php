<?php

use App\Actions\Workouts\ParseSetgraphWorkout;

$share = <<<'TEXT'
Lat Pulldown • 12 rep: 32, 36, 36 kg
Trx push up • 3 sets: 12 rep

Other • 38 min

Tracked on Setgraph
TEXT;

it('pulls the sets and the stated length out of a share', function () use ($share) {
    $workout = app(ParseSetgraphWorkout::class)($share);

    expect($workout->sets)->toHaveCount(6)
        ->and($workout->duration)->toBe(2280)
        ->and($workout->label)->toBe('Other')
        // Bodyweight sets add nothing to volume: 12 x (32 + 36 + 36).
        ->and($workout->volume())->toBe(1248.0);
});

it('reads an hours summary as well as minutes', function () {
    $workout = app(ParseSetgraphWorkout::class)("Squat • 5 rep 60 kg\n\nStrength • 2 hours");

    expect($workout->duration)->toBe(7200);
});

it('reads the hours and minutes Setgraph writes for a session past the hour', function (string $summary) {
    $workout = app(ParseSetgraphWorkout::class)("Squat • 5 rep 60 kg\n\n{$summary}");

    expect($workout->duration)->toBe(3840);
})->with(['Pull • 1 h 4 min', 'Pull • 1h 4min', 'Pull • 1 hr 4 min']);

it('does not read an exercise line as the summary when it states a time', function () {
    $workout = app(ParseSetgraphWorkout::class)("Plank • 2 min\n\nCore • 25 min");

    expect($workout->duration)->toBe(1500)
        ->and($workout->label)->toBe('Core');
});

it('leaves duration null when no summary line is shared', function () {
    $workout = app(ParseSetgraphWorkout::class)('Squat • 5 rep 60 kg');

    expect($workout->duration)->toBeNull()
        ->and($workout->isEmpty())->toBeFalse();
});

it('reports a share with no parsable sets as empty', function () {
    $workout = app(ParseSetgraphWorkout::class)('Tracked on Setgraph');

    expect($workout->isEmpty())->toBeTrue();
});
