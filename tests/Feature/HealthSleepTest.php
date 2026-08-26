<?php

use App\Models\Sleep;
use App\Support\Health\SleepAggregator;

function sleepPayload(): array
{
    return [
        'data' => [
            'metrics' => [
                [
                    'name' => 'sleep_analysis',
                    'units' => 'hr',
                    'data' => [
                        // Night of 22 Jun (bedtime after midnight on the 23rd), Oura.
                        ['value' => 'In Bed', 'source' => 'Oura', 'start' => '2026-06-23 01:24:00 +0100', 'end' => '2026-06-23 05:00:00 +0100'],
                        ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-06-23 01:24:00 +0100', 'end' => '2026-06-23 02:24:00 +0100'],
                        ['value' => 'Deep', 'source' => 'Oura', 'start' => '2026-06-23 02:24:00 +0100', 'end' => '2026-06-23 02:54:00 +0100'],
                        ['value' => 'REM', 'source' => 'Oura', 'start' => '2026-06-23 02:54:00 +0100', 'end' => '2026-06-23 03:24:00 +0100'],
                        ['value' => 'Awake', 'source' => 'Oura', 'start' => '2026-06-23 03:24:00 +0100', 'end' => '2026-06-23 03:34:00 +0100'],
                        ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-06-23 03:34:00 +0100', 'end' => '2026-06-23 05:00:00 +0100'],
                        // Same night, Apple Watch — must lose to Oura.
                        ['value' => 'Core', 'source' => "Taylor’s Apple\u{a0}Watch", 'start' => '2026-06-23 01:30:00 +0100', 'end' => '2026-06-23 04:00:00 +0100'],
                        // Night of 24 Jun, Apple Watch only — fallback source.
                        ['value' => 'Core', 'source' => "Taylor’s Apple\u{a0}Watch", 'start' => '2026-06-24 23:00:00 +0100', 'end' => '2026-06-25 00:00:00 +0100'],
                        ['value' => 'Deep', 'source' => "Taylor’s Apple\u{a0}Watch", 'start' => '2026-06-25 00:00:00 +0100', 'end' => '2026-06-25 00:30:00 +0100'],
                        ['value' => 'REM', 'source' => "Taylor’s Apple\u{a0}Watch", 'start' => '2026-06-25 00:30:00 +0100', 'end' => '2026-06-25 01:00:00 +0100'],
                    ],
                ],
            ],
        ],
    ];
}

it('prefers Oura and computes stage seconds per night', function () {
    $records = app(SleepAggregator::class)->aggregate(sleepPayload()['data']['metrics'][0]['data']);

    // Apple dating: a 1:24am bedtime is dated to that morning, not the prior evening.
    expect($records)->toHaveKeys(['2026-06-23', '2026-06-25']);

    $oura = $records['2026-06-23'];
    expect($oura['source'])->toBe('oura')
        ->and($oura['bedtime'])->toBe('2026-06-23 01:24:00')
        ->and($oura['wake_time'])->toBe('2026-06-23 05:00:00')
        ->and($oura['core'])->toBe(8760)   // 3600 + 5160
        ->and($oura['deep'])->toBe(1800)
        ->and($oura['rem'])->toBe(1800)
        ->and($oura['awake'])->toBe(600)
        ->and($oura['duration'])->toBe(12360); // window 12960 - awake 600

    expect($records['2026-06-25']['source'])->toBe('apple_watch')
        ->and($records['2026-06-25']['duration'])->toBe(7200);
});

it('counts unstaged "Asleep" segments as sleep instead of dropping them', function () {
    $segments = [
        ['value' => 'Asleep', 'source' => "Taylor’s Apple\u{a0}Watch", 'start' => '2026-06-06 23:00:00 +0100', 'end' => '2026-06-07 02:00:00 +0100'],
        ['value' => 'Awake', 'source' => "Taylor’s Apple\u{a0}Watch", 'start' => '2026-06-07 02:00:00 +0100', 'end' => '2026-06-07 02:10:00 +0100'],
        ['value' => 'Asleep', 'source' => "Taylor’s Apple\u{a0}Watch", 'start' => '2026-06-07 02:10:00 +0100', 'end' => '2026-06-07 06:00:00 +0100'],
    ];

    $records = app(SleepAggregator::class)->aggregate($segments);
    $night = reset($records);

    expect($night['core'])->toBe(24600)   // 3h + 3h50m of unstaged sleep
        ->and($night['awake'])->toBe(600)
        ->and($night['duration'])->toBe(24600); // 7h window - 10m awake
});

it('drops a stray evening reading and keeps the real night session', function () {
    $segments = [
        // Stray evening blip in the same Apple sleep-day window — must not become bedtime.
        ['value' => 'Awake', 'source' => 'Oura', 'start' => '2026-06-19 20:00:00 +0100', 'end' => '2026-06-19 20:02:00 +0100'],
        // The real night sleep, well beyond the 3h session gap.
        ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-06-20 00:30:00 +0100', 'end' => '2026-06-20 02:30:00 +0100'],
        ['value' => 'Deep', 'source' => 'Oura', 'start' => '2026-06-20 02:30:00 +0100', 'end' => '2026-06-20 03:30:00 +0100'],
        ['value' => 'REM', 'source' => 'Oura', 'start' => '2026-06-20 03:30:00 +0100', 'end' => '2026-06-20 05:13:00 +0100'],
    ];

    $records = app(SleepAggregator::class)->aggregate($segments);

    expect($records)->toHaveCount(1);
    $night = reset($records);
    expect($night['bedtime'])->toBe('2026-06-20 00:30:00')
        ->and($night['wake_time'])->toBe('2026-06-20 05:13:00')
        ->and($night['duration'])->toBe(16980); // 4h43m, no evening contamination
});

it('keeps the longer night when a real nap is present the same sleep-day', function () {
    $segments = [
        // A 40-minute evening nap, same Apple sleep-day as the night.
        ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-06-19 19:00:00 +0100', 'end' => '2026-06-19 19:40:00 +0100'],
        // The night sleep — longer, so it wins.
        ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-06-19 23:00:00 +0100', 'end' => '2026-06-20 06:00:00 +0100'],
    ];

    $records = app(SleepAggregator::class)->aggregate($segments);
    $night = reset($records);

    expect($night['bedtime'])->toBe('2026-06-19 23:00:00')
        ->and($night['core'])->toBe(25200); // 7h night only, nap excluded
});

it('scores every night, oldest first, from what is stored', function () {
    $nights = [
        ['2026-07-01', '2026-07-01 23:00:00', '2026-07-02 07:00:00', 28800, 300, 5400, 10800, 5400],
        ['2026-07-02', '2026-07-02 23:10:00', '2026-07-03 07:00:00', 27000, 600, 5000, 12000, 4000],
        ['2026-07-03', '2026-07-03 02:30:00', '2026-07-03 06:30:00', 14400, 1800, 1200, 9000, 1200], // short, late, broken
    ];

    foreach ($nights as [$occurredAt, $bedtime, $wakeTime, $duration, $awake, $rem, $core, $deep]) {
        Sleep::factory()->create([
            'occurred_at' => $occurredAt, 'bedtime' => $bedtime, 'wake_time' => $wakeTime,
            'duration' => $duration, 'awake' => $awake, 'rem' => $rem, 'core' => $core, 'deep' => $deep,
            'source' => 'oura',
            'stages' => json_encode([
                ['stage' => 'awake', 'start' => $bedtime, 'end' => $bedtime],
                ['stage' => 'core', 'start' => $bedtime, 'end' => $wakeTime],
            ]),
        ]);
    }

    $this->artisan('health:sleep', ['--score' => true])->assertSuccessful();

    $good = Sleep::query()->whereDate('occurred_at', '2026-07-01')->first();
    $bad = Sleep::query()->whereDate('occurred_at', '2026-07-03')->first();

    expect($good->score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
        ->and($good->score)->toBeGreaterThan($bad->score)
        ->and($good->duration_score + $good->bedtime_score + $good->interruption_score)->toBe($good->score);
});

it('imports nights into the database, replacing an overlapping night', function () {
    $dir = sys_get_temp_dir().'/health_sleep_'.uniqid();
    mkdir($dir);
    $jsonPath = "{$dir}/payload.json";

    file_put_contents($jsonPath, json_encode(sleepPayload()));

    // An existing night the payload also covers, and one it does not touch.
    // Sleeps are matched on the period they cover, so the overlapping one needs
    // a bedtime and wake time that actually overlap the payload's night.
    Sleep::factory()->create(['occurred_at' => '2026-06-20 07:00:00', 'bedtime' => '2026-06-19 23:00:00', 'wake_time' => '2026-06-20 07:00:00', 'source' => 'clock', 'duration' => 25200]);
    Sleep::factory()->create(['occurred_at' => '2026-06-23 04:10:00', 'bedtime' => '2026-06-23 01:20:00', 'wake_time' => '2026-06-23 04:10:00', 'source' => 'clock', 'duration' => 10000]);

    $this->artisan('health:sleep', ['--file' => $jsonPath])->assertSuccessful();

    // Overlapping night replaced by the Oura reading.
    expect(Sleep::query()->whereDate('occurred_at', '2026-06-23')->value('source'))->toBe('oura')
        ->and(Sleep::query()->whereDate('occurred_at', '2026-06-23')->value('duration'))->toBe(12360);

    // The unrelated night is left alone, and the new one is added.
    expect(Sleep::query()->whereDate('occurred_at', '2026-06-20')->value('source'))->toBe('clock')
        ->and(Sleep::query()->whereDate('occurred_at', '2026-06-25')->exists())->toBeTrue();

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

/*
 * A nap and a night can end on the same date, which a day-granular key let
 * overwrite each other. Four days in the real data hold both.
 */
it('keeps a nap and a night that fall on the same day', function () {
    $night = Sleep::factory()->create([
        'occurred_at' => '2026-06-10 07:30:00',
        'bedtime' => '2026-06-09 23:30:00',
        'wake_time' => '2026-06-10 07:30:00',
        'duration' => 28800,
    ]);

    $nap = Sleep::factory()->create([
        'occurred_at' => '2026-06-10 15:10:00',
        'bedtime' => '2026-06-10 14:00:00',
        'wake_time' => '2026-06-10 15:10:00',
        'duration' => 4200,
    ]);

    expect(Sleep::query()->whereDate('occurred_at', '2026-06-10')->count())->toBe(2)
        ->and($nap->isNap())->toBeTrue()
        ->and($night->isNap())->toBeFalse();
});

// The rule needs all three conditions: an early night starts in the same hours
// as a nap, and a short night is as brief as one.
it('tells a nap apart from an early night and a short night', function (string $bedtime, string $wake, int $duration, bool $expected) {
    $sleep = Sleep::factory()->make(['bedtime' => $bedtime, 'wake_time' => $wake, 'duration' => $duration]);

    expect($sleep->isNap())->toBe($expected);
})->with([
    'afternoon nap' => ['2026-03-07 15:28:50', '2026-03-07 16:56:57', 5287, true],
    'evening doze' => ['2025-12-07 18:09:27', '2025-12-07 20:23:37', 8050, true],
    'early night, same hours' => ['2026-07-09 18:00:29', '2026-07-10 08:11:59', 44490, false],
    'short night, crosses midnight' => ['2026-01-19 19:54:01', '2026-01-20 04:53:31', 19170, false],
    'ordinary night' => ['2025-06-02 23:14:45', '2025-06-03 04:57:45', 20430, false],
]);

// A nap is kept but not shown: the day's sleep card should be the night.
it('leaves a nap off the timeline while keeping the row', function () {
    $nap = Sleep::factory()->create([
        'occurred_at' => '2026-06-10 15:10:00',
        'bedtime' => '2026-06-10 14:00:00',
        'wake_time' => '2026-06-10 15:10:00',
        'duration' => 4200,
    ]);

    expect($nap->fresh())->not->toBeNull()
        ->and($nap->timelineEntry()->exists())->toBeFalse();
});
