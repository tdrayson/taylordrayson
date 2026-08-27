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

it('penalises interruptions by the share of the night spent awake', function () {
    // 50m awake inside 8h30m in bed, so a tenth of it.
    $result = (new SleepScore)->score(night(['awake' => 3000]));

    expect($result['interruption_score'])->toBe(17);
});

// The same fifty minutes is a fifth of a short night and a fifteenth of a long
// one, and scoring them alike drove this component to nothing on every lie-in.
it('reads the same awake time differently against a short night and a long one', function () {
    $short = (new SleepScore)->score(night(['duration' => 12600, 'awake' => 3000]));
    $long = (new SleepScore)->score(night(['duration' => 39600, 'awake' => 3000]));

    expect($short['interruption_score'])->toBeLessThan($long['interruption_score'])
        ->and($long['interruption_score'])->toBe(19);
});

it('does not treat a very long night as a better one', function () {
    $onTarget = (new SleepScore)->score(night(['duration' => 28200, 'rem' => 4500, 'deep' => 3000]));
    // 11h asleep: over three hours past the target, at three points an hour.
    $long = (new SleepScore)->score(night(['duration' => 39600, 'rem' => 4500, 'deep' => 3000]));

    expect($onTarget['duration_score'])->toBe(50)
        ->and($long['duration_score'])->toBe(41);
});

// Measured against what was slept, a lie-in had to produce proportionally more
// deep sleep to escape the penalty, so sleeping longer scored worse.
it('judges deep and REM against the target, not against a long night', function () {
    // Deep and REM sufficient for the target, but under a tenth and a seventh
    // of eleven hours, which is what the old rule measured them against.
    $modest = (new SleepScore)->score(night(['duration' => 39600, 'rem' => 4500, 'deep' => 3000]));
    $ample = (new SleepScore)->score(night(['duration' => 39600, 'rem' => 9900, 'deep' => 6600]));

    expect($modest['duration_score'])->toBe($ample['duration_score']);
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
