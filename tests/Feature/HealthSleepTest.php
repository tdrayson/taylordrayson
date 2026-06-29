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

it('resplits an existing merged row from its stored stages and leaves clean rows alone', function () {
    $dir = sys_get_temp_dir().'/health_resplit_'.uniqid();
    mkdir($dir);
    $csvPath = "{$dir}/sleep.csv";

    $headers = ['occurred_at', 'bedtime', 'wake_time', 'duration', 'awake', 'rem', 'core', 'deep', 'source', 'stages'];
    $goodStages = json_encode([['stage' => 'core', 'start' => '2026-06-30 23:00:00', 'end' => '2026-07-01 06:00:00']]);
    $badStages = json_encode([
        ['stage' => 'awake', 'start' => '2026-07-01 14:00:00', 'end' => '2026-07-01 14:02:00'],
        ['stage' => 'core', 'start' => '2026-07-02 00:30:00', 'end' => '2026-07-02 05:30:00'],
    ]);

    $handle = fopen($csvPath, 'w');
    fputcsv($handle, $headers);
    fputcsv($handle, ['2026-06-30', '2026-06-30 23:00:00', '2026-07-01 06:00:00', '25200', '0', '0', '25200', '0', 'oura', $goodStages]);
    fputcsv($handle, ['2026-07-01', '2026-07-01 14:00:00', '2026-07-02 05:30:00', '55080', '0', '0', '55080', '0', 'apple_watch', $badStages]);
    fclose($handle);

    $this->artisan('health:sleep', ['--resplit' => true, '--csv' => $csvPath])->assertSuccessful();

    $rows = collect(array_map('str_getcsv', file($csvPath, FILE_IGNORE_NEW_LINES)))->skip(1)->keyBy(fn (array $r): string => $r[0]);

    // Merged row corrected: bedtime is the night, not the 2pm blip.
    expect($rows['2026-07-01'][1])->toBe('2026-07-02 00:30:00')
        ->and($rows['2026-07-01'][3])->toBe('18000');
    // Clean row untouched.
    expect($rows['2026-06-30'][3])->toBe('25200')
        ->and($rows['2026-06-30'][1])->toBe('2026-06-30 23:00:00');

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('re-dates rows to the Apple sleep-day, moving the DB and dropping nap collisions', function () {
    $dir = sys_get_temp_dir().'/health_redate_'.uniqid();
    mkdir($dir);
    $csvPath = "{$dir}/sleep.csv";

    $rows = [
        // occurred_at, bedtime, wake_time, duration, source
        ['2026-07-09', '2026-07-10 01:00:00', '2026-07-10 08:00:00', 25200, 'oura'],  // morning sleep -> July 10
        ['2026-07-10', '2026-07-10 13:00:00', '2026-07-10 14:00:00', 3600, 'oura'],   // afternoon nap -> July 10 (loses)
        ['2026-07-11', '2026-07-11 23:00:00', '2026-07-12 05:00:00', 20000, 'oura'],  // evening -> July 12
    ];

    $headers = ['occurred_at', 'bedtime', 'wake_time', 'duration', 'awake', 'rem', 'core', 'deep', 'source', 'stages'];
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, $headers);

    foreach ($rows as [$occurredAt, $bedtime, $wakeTime, $duration, $source]) {
        Sleep::factory()->create([
            'occurred_at' => $occurredAt, 'bedtime' => $bedtime, 'wake_time' => $wakeTime,
            'duration' => $duration, 'awake' => 0, 'rem' => 0, 'core' => $duration, 'deep' => 0,
            'source' => $source, 'stages' => null,
        ]);
        fputcsv($handle, [$occurredAt, $bedtime, $wakeTime, $duration, 0, 0, $duration, 0, $source, '']);
    }

    fclose($handle);

    $this->artisan('health:sleep', ['--redate' => true, '--csv' => $csvPath])->assertSuccessful();

    $byNight = collect(array_map('str_getcsv', file($csvPath, FILE_IGNORE_NEW_LINES)))->skip(1)->keyBy(fn (array $r): string => $r[0]);
    expect($byNight)->toHaveKeys(['2026-07-10', '2026-07-12'])
        ->and($byNight)->not->toHaveKey('2026-07-09')
        ->and($byNight['2026-07-10'][3])->toBe('25200'); // the real sleep, not the nap

    expect(Sleep::query()->count())->toBe(2)
        ->and(Sleep::query()->whereDate('occurred_at', '2026-07-10')->exists())->toBeTrue()
        ->and(Sleep::query()->whereDate('occurred_at', '2026-07-09')->exists())->toBeFalse();

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('computes and stores an Apple-style sleep score on every row', function () {
    $dir = sys_get_temp_dir().'/health_score_'.uniqid();
    mkdir($dir);
    $csvPath = "{$dir}/sleep.csv";

    $headers = ['occurred_at', 'bedtime', 'wake_time', 'duration', 'awake', 'rem', 'core', 'deep', 'source', 'stages'];
    $nights = [
        ['2026-07-01', '2026-07-01 23:00:00', '2026-07-02 07:00:00', 28800, 300, 5400, 10800, 5400],
        ['2026-07-02', '2026-07-02 23:10:00', '2026-07-03 07:00:00', 27000, 600, 5000, 12000, 4000],
        ['2026-07-03', '2026-07-03 02:30:00', '2026-07-03 06:30:00', 14400, 1800, 1200, 9000, 1200], // short, late, broken
    ];

    $handle = fopen($csvPath, 'w');
    fputcsv($handle, $headers);

    foreach ($nights as [$occurredAt, $bedtime, $wakeTime, $duration, $awake, $rem, $core, $deep]) {
        $stages = json_encode([
            ['stage' => 'awake', 'start' => $bedtime, 'end' => $bedtime],
            ['stage' => 'core', 'start' => $bedtime, 'end' => $wakeTime],
        ]);
        Sleep::factory()->create([
            'occurred_at' => $occurredAt, 'bedtime' => $bedtime, 'wake_time' => $wakeTime,
            'duration' => $duration, 'awake' => $awake, 'rem' => $rem, 'core' => $core, 'deep' => $deep,
            'source' => 'oura', 'stages' => $stages,
        ]);
        fputcsv($handle, [$occurredAt, $bedtime, $wakeTime, $duration, $awake, $rem, $core, $deep, 'oura', $stages]);
    }

    fclose($handle);

    $this->artisan('health:sleep', ['--score' => true, '--csv' => $csvPath])->assertSuccessful();

    $lines = array_map('str_getcsv', file($csvPath, FILE_IGNORE_NEW_LINES));
    expect($lines[0])->toContain('score', 'duration_score', 'bedtime_score', 'interruption_score');

    // The good first night should clearly outscore the short/late/broken third night.
    $good = Sleep::query()->whereDate('occurred_at', '2026-07-01')->first();
    $bad = Sleep::query()->whereDate('occurred_at', '2026-07-03')->first();
    expect($good->score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
        ->and($good->score)->toBeGreaterThan($bad->score)
        ->and($good->duration_score + $good->bedtime_score + $good->interruption_score)->toBe($good->score);

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('imports nights into the database and merges the CSV without losing existing rows', function () {
    $dir = sys_get_temp_dir().'/health_sleep_'.uniqid();
    mkdir($dir);
    $jsonPath = "{$dir}/payload.json";
    $csvPath = "{$dir}/sleep.csv";

    file_put_contents($jsonPath, json_encode(sleepPayload()));

    $header = 'occurred_at,bedtime,wake_time,duration,awake,rem,core,deep,source,stages';
    file_put_contents($csvPath, implode("\n", [
        $header,
        '2026-06-20,"2026-06-20 23:00:00","2026-06-21 06:00:00",25200,0,,,,clock,',
        '2026-06-23,"2026-06-23 01:00:00","2026-06-23 04:00:00",10000,0,,,,clock,',
    ])."\n");

    $this->artisan('health:sleep', ['--file' => $jsonPath, '--csv' => $csvPath])->assertSuccessful();

    expect(Sleep::query()->whereDate('occurred_at', '2026-06-23')->value('source'))->toBe('oura');
    expect(Sleep::query()->whereDate('occurred_at', '2026-06-23')->value('duration'))->toBe(12360);
    expect(Sleep::query()->count())->toBe(2);

    $rows = array_map('str_getcsv', file($csvPath, FILE_IGNORE_NEW_LINES));
    $byNight = collect($rows)->skip(1)->keyBy(fn (array $row): string => $row[0]);

    // Existing unrelated night untouched.
    expect($byNight['2026-06-20'][8])->toBe('clock')
        ->and($byNight['2026-06-20'][3])->toBe('25200');

    // Overlapping night replaced by the Oura import.
    expect($byNight['2026-06-23'][8])->toBe('oura')
        ->and($byNight['2026-06-23'][3])->toBe('12360');

    // New night appended.
    expect($byNight)->toHaveKey('2026-06-25');

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});
