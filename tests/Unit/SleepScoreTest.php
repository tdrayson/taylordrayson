<?php

use App\Support\Health\SleepScore;

function night(array $overrides = []): array
{
    return [...[
        'duration' => 28800,        // 8h asleep
        'awake' => 300,             // 5m
        'rem' => 5400,              // 1h30m
        'core' => 10800,
        'deep' => 5400,             // 1h30m
        'wake_events' => 1,
        'bedtime_minutes' => 300,   // 23:00 (minutes after 6pm)
        'baseline_minutes' => 300,
    ], ...$overrides];
}

it('gives a full score to an ideal night', function () {
    expect((new SleepScore)->score(night()))->toBe([
        'score' => 100,
        'duration_score' => 50,
        'bedtime_score' => 30,
        'interruption_score' => 20,
    ]);
});

it('penalises short sleep on the duration component', function () {
    // 5h asleep, well-staged so no deep/REM penalty.
    $result = (new SleepScore)->score(night(['duration' => 18000, 'rem' => 3000, 'deep' => 2000]));

    expect($result['duration_score'])->toBe(29)
        ->and($result['bedtime_score'])->toBe(30)
        ->and($result['score'])->toBe(79);
});

it('penalises a late bedtime against the baseline', function () {
    // 2h later than the 23:00 baseline.
    $result = (new SleepScore)->score(night(['bedtime_minutes' => 420]));

    expect($result['bedtime_score'])->toBe(7)
        ->and($result['score'])->toBe(77);
});

it('penalises interruptions for awake time and wake-ups', function () {
    $result = (new SleepScore)->score(night(['awake' => 3000, 'wake_events' => 8]));

    expect($result['interruption_score'])->toBe(7)
        ->and($result['score'])->toBe(87);
});

it('docks deep and REM when a staged night is light on both', function () {
    $result = (new SleepScore)->score(night(['deep' => 600, 'rem' => 600]));

    expect($result['duration_score'])->toBe(40); // 50 - 5 (low deep) - 5 (low REM)
});

it('does not penalise stages on an unstaged night', function () {
    // All sleep is core (Apple Watch unstaged) — no deep/REM to assess.
    $result = (new SleepScore)->score(night(['deep' => 0, 'rem' => 0, 'core' => 28800]));

    expect($result['duration_score'])->toBe(50);
});

it('awards full bedtime points when there is no baseline yet', function () {
    $result = (new SleepScore)->score(night(['baseline_minutes' => null, 'bedtime_minutes' => 600]));

    expect($result['bedtime_score'])->toBe(30);
});
