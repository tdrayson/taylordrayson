<?php

use App\Models\Activity;
use App\Support\Health\HeartRateMatcher;
use Illuminate\Database\Eloquent\Collection;

/**
 * @param  list<array{source: string, at: string, avg: int, max?: int}>  $samples
 */
function heartRatePayload(array $samples): array
{
    return [
        'data' => [
            'metrics' => [
                [
                    'name' => 'heart_rate',
                    'units' => 'count/min',
                    'data' => array_map(fn (array $sample): array => [
                        'start' => $sample['at'],
                        'end' => $sample['at'],
                        'date' => $sample['at'],
                        'source' => $sample['source'],
                        'Avg' => $sample['avg'],
                        'Min' => $sample['avg'],
                        'Max' => $sample['max'] ?? $sample['avg'],
                    ], $samples),
                ],
            ],
        ],
    ];
}

it('windows samples into an activity and prefers the dominant source', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-06-23 08:00:00',
        'duration' => 600,
        'meta' => [],
        'heart_rate' => null,
    ]);

    $samples = [
        ['time' => strtotime('2026-06-23 08:00:00 +0000'), 'avg' => 100, 'max' => 105, 'source' => 'Apple Watch'],
        ['time' => strtotime('2026-06-23 08:01:00 +0000'), 'avg' => 110, 'max' => 115, 'source' => 'Apple Watch'],
        ['time' => strtotime('2026-06-23 08:02:00 +0000'), 'avg' => 120, 'max' => 160, 'source' => 'Apple Watch'],
        // Oura overlaps but reports fewer samples in-window, so it must lose.
        ['time' => strtotime('2026-06-23 08:00:00 +0000'), 'avg' => 60, 'max' => 60, 'source' => 'Oura'],
        // Outside the 10-minute window — must be ignored.
        ['time' => strtotime('2026-06-23 09:00:00 +0000'), 'avg' => 200, 'max' => 200, 'source' => 'Apple Watch'],
    ];

    /** @var Collection<int, Activity> $activities */
    $activities = Activity::query()->get()->keyBy('id');
    $matched = app(HeartRateMatcher::class)->match($samples, $activities);

    expect($matched)->toHaveKey($activity->id);
    expect($matched[$activity->id]['avg'])->toBe(110)   // (100 + 110 + 120) / 3
        ->and($matched[$activity->id]['max'])->toBe(160)
        ->and($matched[$activity->id]['series'])->toHaveCount(3);
});

it('matches samples to a BST activity using its stored timezone', function () {
    // occurred_at is stored as LOCAL wall-clock (app tz is UTC), with the real
    // zone in the timezone column. This activity ran 14:00-14:10 BST, which is
    // 13:00-13:10 UTC. Health Auto Export stamps samples in real UTC, so the
    // matcher must interpret occurred_at through Europe/London to find them.
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-07-15 14:00:00', // local wall-clock (BST)
        'timezone' => 'Europe/London',
        'duration' => 600,
        'meta' => [],
        'heart_rate' => null,
    ]);

    $samples = [
        ['time' => strtotime('2026-07-15 13:00:00 +0000'), 'avg' => 100, 'max' => 105, 'source' => 'Apple Watch'],
        ['time' => strtotime('2026-07-15 13:05:00 +0000'), 'avg' => 110, 'max' => 115, 'source' => 'Apple Watch'],
        ['time' => strtotime('2026-07-15 13:10:00 +0000'), 'avg' => 120, 'max' => 160, 'source' => 'Apple Watch'],
    ];

    /** @var Collection<int, Activity> $activities */
    $activities = Activity::query()->get()->keyBy('id');
    $matched = app(HeartRateMatcher::class)->match($samples, $activities);

    expect($matched)->toHaveKey($activity->id);
    expect($matched[$activity->id]['series'])->toHaveCount(3)
        ->and($matched[$activity->id]['avg'])->toBe(110);
});

it('matches samples to a GMT activity where local time equals UTC', function () {
    // The winter control: Europe/London is UTC+0 in January, so the wall-clock
    // start and the real UTC start coincide and the window lines up directly.
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-01-15 14:00:00',
        'timezone' => 'Europe/London',
        'duration' => 600,
        'meta' => [],
        'heart_rate' => null,
    ]);

    $samples = [
        ['time' => strtotime('2026-01-15 14:00:00 +0000'), 'avg' => 100, 'max' => 105, 'source' => 'Apple Watch'],
        ['time' => strtotime('2026-01-15 14:05:00 +0000'), 'avg' => 110, 'max' => 115, 'source' => 'Apple Watch'],
        ['time' => strtotime('2026-01-15 14:10:00 +0000'), 'avg' => 120, 'max' => 160, 'source' => 'Apple Watch'],
    ];

    /** @var Collection<int, Activity> $activities */
    $activities = Activity::query()->get()->keyBy('id');
    $matched = app(HeartRateMatcher::class)->match($samples, $activities);

    expect($matched)->toHaveKey($activity->id);
    expect($matched[$activity->id]['series'])->toHaveCount(3);
});

it('attaches heart-rate to the matching activity and mirrors only its CSV row', function () {
    $dir = sys_get_temp_dir().'/health_hr_'.uniqid();
    mkdir($dir);
    $jsonPath = "{$dir}/payload.json";
    $csvPath = "{$dir}/activities.csv";

    $matched = Activity::factory()->create([
        'occurred_at' => '2026-06-23 08:00:00', 'duration' => 600, 'meta' => [], 'heart_rate' => null,
    ]);
    Activity::factory()->create([
        'occurred_at' => '2026-06-20 08:00:00', 'duration' => 600, 'meta' => [], 'heart_rate' => null,
    ]);

    file_put_contents($jsonPath, json_encode(heartRatePayload([
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:00:00 +0000', 'avg' => 100, 'max' => 105],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:01:00 +0000', 'avg' => 110, 'max' => 115],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:02:00 +0000', 'avg' => 120, 'max' => 160],
    ])));

    $header = 'occurred_at,type,name,duration,calories,distance,average_heart_rate,max_heart_rate,heart_rate,platform_type,platform_id,meta';
    file_put_contents($csvPath, implode("\n", [
        $header,
        '"2026-06-23 08:00:00",run,"Morning Run",600,90,2.5,,,,strava,1,"[]"',
        '"2026-06-20 08:00:00",run,"Other Run",600,90,2.5,,,,strava,2,"[]"',
    ])."\n");

    $this->artisan('health:heart_rate', ['--file' => $jsonPath, '--csv' => $csvPath])->assertSuccessful();

    $matched->refresh();
    expect($matched->average_heart_rate)->toBe(110)
        ->and($matched->max_heart_rate)->toBe(160)
        ->and($matched->heart_rate)->toHaveCount(3);

    $rows = collect(array_map('str_getcsv', file($csvPath, FILE_IGNORE_NEW_LINES)))
        ->skip(1)
        ->keyBy(fn (array $row): string => $row[0]);

    // Matched row gets the columns; the unrelated row stays untouched.
    expect($rows['2026-06-23 08:00:00'][6])->toBe('110')
        ->and($rows['2026-06-23 08:00:00'][7])->toBe('160')
        ->and($rows['2026-06-23 08:00:00'][8])->not->toBe('')      // series written
        ->and($rows['2026-06-20 08:00:00'][6])->toBe('')           // unrelated row untouched
        ->and($rows['2026-06-20 08:00:00'][8])->toBe('')
        ->and($rows['2026-06-20 08:00:00'][11])->toBe('[]');

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('merges batched payloads into one series and defends the prior peak', function () {
    $dir = sys_get_temp_dir().'/health_hr_batch_'.uniqid();
    mkdir($dir);
    $csvPath = "{$dir}/activities.csv";
    $firstPath = "{$dir}/first.json";
    $secondPath = "{$dir}/second.json";

    $activity = Activity::factory()->create([
        'occurred_at' => '2026-06-23 08:00:00', 'duration' => 900, 'meta' => [], 'heart_rate' => null,
    ]);

    file_put_contents($csvPath, "occurred_at,type,name,duration,calories,distance,average_heart_rate,max_heart_rate,heart_rate,platform_type,platform_id,meta\n"
        .'"2026-06-23 08:00:00",run,"Morning Run",900,90,2.5,,,,strava,1,"[]"'."\n");

    file_put_contents($firstPath, json_encode(heartRatePayload([
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:00:00 +0000', 'avg' => 100, 'max' => 105],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:01:00 +0000', 'avg' => 110, 'max' => 115],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:02:00 +0000', 'avg' => 120, 'max' => 130],
    ])));
    file_put_contents($secondPath, json_encode(heartRatePayload([
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:05:00 +0000', 'avg' => 130, 'max' => 140],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:06:00 +0000', 'avg' => 140, 'max' => 150],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:07:00 +0000', 'avg' => 150, 'max' => 200],
    ])));

    $this->artisan('health:heart_rate', ['--file' => $firstPath, '--csv' => $csvPath])->assertSuccessful();
    $this->artisan('health:heart_rate', ['--file' => $secondPath, '--csv' => $csvPath])->assertSuccessful();

    // Both batches' samples merge into one series; the first batch already
    // filled the scalars, so they hold until an explicit recompute.
    $activity->refresh();
    expect($activity->heart_rate)->toHaveCount(6)
        ->and($activity->average_heart_rate)->toBe(110)   // mean of the first batch
        ->and($activity->max_heart_rate)->toBe(130);      // peak of the first batch

    // --overwrite recomputes both from the full merged series.
    $this->artisan('health:heart_rate', ['--file' => $secondPath, '--csv' => $csvPath, '--overwrite' => true])->assertSuccessful();
    $activity->refresh();
    expect($activity->average_heart_rate)->toBe(125)      // mean of all six
        ->and($activity->max_heart_rate)->toBe(200);      // peak from the second batch

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('preserves a source-provided average but still attaches the series', function () {
    $dir = sys_get_temp_dir().'/health_hr_keep_'.uniqid();
    mkdir($dir);
    $jsonPath = "{$dir}/payload.json";
    $csvPath = "{$dir}/activities.csv";

    // An activity that already carries Strava's average/max and no series.
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-06-23 08:00:00', 'duration' => 600, 'meta' => [],
        'average_heart_rate' => 159, 'max_heart_rate' => 208, 'heart_rate' => null,
    ]);

    file_put_contents($csvPath, "occurred_at,type,name,duration,calories,distance,average_heart_rate,max_heart_rate,heart_rate,platform_type,platform_id,meta\n"
        .'"2026-06-23 08:00:00",run,"Morning Run",600,90,2.5,159,208,,strava,1,"[]"'."\n");

    file_put_contents($jsonPath, json_encode(heartRatePayload([
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:00:00 +0000', 'avg' => 100, 'max' => 105],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:01:00 +0000', 'avg' => 110, 'max' => 250],
    ])));

    $this->artisan('health:heart_rate', ['--file' => $jsonPath, '--csv' => $csvPath])->assertSuccessful();

    $activity->refresh();
    // Strava's scalars are kept, even though the samples imply a different mean/peak.
    expect((int) $activity->average_heart_rate)->toBe(159)
        ->and((int) $activity->max_heart_rate)->toBe(208)
        ->and($activity->heart_rate)->toHaveCount(2);

    // --overwrite then recomputes both from the samples.
    $this->artisan('health:heart_rate', ['--file' => $jsonPath, '--csv' => $csvPath, '--overwrite' => true])->assertSuccessful();
    $activity->refresh();
    expect((int) $activity->average_heart_rate)->toBe(105) // (100 + 110) / 2
        ->and((int) $activity->max_heart_rate)->toBe(250);

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('fills a missing max from samples while preserving an existing average', function () {
    $dir = sys_get_temp_dir().'/health_hr_partial_'.uniqid();
    mkdir($dir);
    $jsonPath = "{$dir}/payload.json";
    $csvPath = "{$dir}/activities.csv";

    // Strava supplied an average but never a max (a common gap on older rows).
    $activity = Activity::factory()->create([
        'occurred_at' => '2026-06-23 08:00:00', 'duration' => 600, 'meta' => [],
        'average_heart_rate' => 130, 'max_heart_rate' => null, 'heart_rate' => null,
    ]);

    file_put_contents($csvPath, "occurred_at,type,name,duration,calories,distance,average_heart_rate,max_heart_rate,heart_rate,platform_type,platform_id,meta\n"
        .'"2026-06-23 08:00:00",run,"Morning Run",600,90,2.5,130,,,strava,1,"[]"'."\n");

    file_put_contents($jsonPath, json_encode(heartRatePayload([
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:00:00 +0000', 'avg' => 100, 'max' => 140],
        ['source' => 'Apple Watch', 'at' => '2026-06-23 08:01:00 +0000', 'avg' => 110, 'max' => 165],
    ])));

    $this->artisan('health:heart_rate', ['--file' => $jsonPath, '--csv' => $csvPath])->assertSuccessful();

    $activity->refresh();
    expect((int) $activity->average_heart_rate)->toBe(130) // Strava's average kept
        ->and((int) $activity->max_heart_rate)->toBe(165)  // missing max filled from samples
        ->and($activity->heart_rate)->toHaveCount(2);

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('caps the stored series with --max-points while keeping the average from every sample', function () {
    $dir = sys_get_temp_dir().'/health_hr_cap_'.uniqid();
    mkdir($dir);
    $jsonPath = "{$dir}/payload.json";
    $csvPath = "{$dir}/activities.csv";

    $activity = Activity::factory()->create([
        'occurred_at' => '2026-06-23 08:00:00', 'duration' => 600, 'meta' => [], 'heart_rate' => null,
    ]);

    file_put_contents($csvPath, "occurred_at,type,name,duration,calories,distance,average_heart_rate,max_heart_rate,heart_rate,platform_type,platform_id,meta\n"
        .'"2026-06-23 08:00:00",run,"Morning Run",600,90,2.5,,,,strava,1,"[]"'."\n");

    $samples = [];
    for ($second = 0; $second < 120; $second++) {
        $samples[] = ['source' => 'Apple Watch', 'at' => date('Y-m-d H:i:s', strtotime('2026-06-23 08:00:00 +0000') + $second).' +0000', 'avg' => 100, 'max' => 100];
    }

    file_put_contents($jsonPath, json_encode(heartRatePayload($samples)));

    $this->artisan('health:heart_rate', ['--file' => $jsonPath, '--csv' => $csvPath, '--max-points' => 10])->assertSuccessful();

    $activity->refresh();
    expect($activity->heart_rate)->toHaveCount(10)
        ->and($activity->average_heart_rate)->toBe(100);

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});
