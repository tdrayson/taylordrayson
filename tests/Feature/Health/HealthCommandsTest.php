<?php

use App\Models\Sleep;
use Illuminate\Support\Facades\File;

it('processes a supplied payload file via health:sleep --file', function () {
    $path = base_path('tests/Fixtures/health/sleep-night.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode(['data' => ['metrics' => [[
        'name' => 'sleep_analysis',
        'data' => [
            ['value' => 'Core', 'source' => 'Oura', 'start' => '2026-01-10 23:30:00 +0000', 'end' => '2026-01-11 03:00:00 +0000'],
            ['value' => 'REM',  'source' => 'Oura', 'start' => '2026-01-11 03:00:00 +0000', 'end' => '2026-01-11 07:00:00 +0000'],
        ],
    ]]]]));

    // Explicit --csv points mirrorToCsv()/scoreAll() at a throwaway temp file
    // instead of the tracked data/sleep.csv, which the command defaults to
    // when --csv is omitted (see SleepProcessorTest for the same pattern).
    $csv = sys_get_temp_dir().'/sleep-cmd-test-'.uniqid().'.csv';

    $this->artisan('health:sleep', ['--file' => $path, '--csv' => $csv])->assertSuccessful();

    expect(Sleep::query()->count())->toBe(1);

    File::delete($path);
});

it('prints guidance when health:sleep runs with no file and no maintenance flag', function () {
    $this->artisan('health:sleep')->assertSuccessful();
});

it('prints guidance when health:heart_rate runs with no file', function () {
    $this->artisan('health:heart_rate')->assertSuccessful();
});
