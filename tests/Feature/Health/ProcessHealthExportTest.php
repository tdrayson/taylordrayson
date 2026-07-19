<?php

use App\Jobs\ProcessHealthExport;
use App\Models\Sleep;
use App\Support\Health\HealthProcessor;
use App\Support\Health\SleepProcessor;

/**
 * The job calls each processor's process() with only the payload, so
 * SleepProcessor::process() resolves its CSV path to the real, tracked
 * data/sleep.csv (it exists, so mirrorToCsv() is NOT a no-op). Renaming it
 * out of the way for the duration of the test makes mirrorToCsv()/scoreAll()
 * treat it as absent (their documented no-op path) without ever touching
 * its contents, and the file is restored immediately after either outcome.
 */
function withoutProductionSleepCsv(Closure $callback): mixed
{
    $path = base_path('data/sleep.csv');
    $hidden = $path.'.test-hidden';

    rename($path, $hidden);

    try {
        return $callback();
    } finally {
        rename($hidden, $path);
    }
}

it('routes sleep_analysis to the sleep processor when the job runs', function () {
    $payload = ['data' => ['metrics' => [[
        'name' => 'sleep_analysis',
        'data' => [
            ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
            ['value' => 'REM', 'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
        ],
    ]]]];

    withoutProductionSleepCsv(function () use ($payload): void {
        (new ProcessHealthExport($payload))->handle();
    });

    expect(Sleep::query()->count())->toBe(1);
});

it('ignores unknown metric names without error', function () {
    $payload = ['data' => ['metrics' => [['name' => 'mindfulness', 'data' => [['value' => 1]]]]]];

    (new ProcessHealthExport($payload))->handle();

    expect(Sleep::query()->count())->toBe(0);
});

it('isolates a failing processor, still runs the others, and throws to trigger a retry', function () {
    // A fake processor standing in for the sleep processor: it claims
    // sleep_analysis but always throws, so we can prove the job isolates the
    // failure and keeps going, without ever touching the real SleepProcessor
    // (and therefore never touching the real data/sleep.csv).
    $failingProcessor = new class implements HealthProcessor
    {
        public function handles(): array
        {
            return ['sleep_analysis'];
        }

        public function process(array $payload): void
        {
            throw new RuntimeException('boom');
        }
    };

    app()->instance(SleepProcessor::class, $failingProcessor);

    // heart_rate with no matching Activity rows in the (in-memory, per-test)
    // database resolves to an empty match set, so HeartRateProcessor::process()
    // never reaches its CSV mirror step either.
    $payload = ['data' => ['metrics' => [
        ['name' => 'sleep_analysis', 'data' => [['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000']]],
        ['name' => 'heart_rate', 'data' => [['start' => '2026-01-10 09:05:00 +0000', 'Avg' => 130, 'Max' => 150, 'source' => 'Apple Watch']]],
    ]]];

    expect(fn () => (new ProcessHealthExport($payload))->handle())->toThrow(RuntimeException::class);
});
