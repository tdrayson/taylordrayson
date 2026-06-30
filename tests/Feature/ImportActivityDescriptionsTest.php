<?php

use App\Models\Activity;

/**
 * Write a CSV in the old-site export shape: ID, Title, "Activity data" (JSON),
 * Content. Each entry's description lives inside the Activity data JSON.
 *
 * @param  array<int, array{strava_id: string, description: string|null}>  $entries
 */
function oldSiteExport(array $entries): string
{
    $path = sys_get_temp_dir().'/old_activities_'.uniqid().'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, ['ID', 'Title', 'Activity data', 'Content'], ',', '"', '');

    foreach ($entries as $i => $entry) {
        $data = json_encode([
            'Activity ID' => $entry['strava_id'],
            'Description' => $entry['description'],
            'Polyline' => 'abc\\def', // a backslash, as real polylines carry
        ]);
        fputcsv($handle, [(string) (1000 + $i), 'Some Title', $data, '.'], ',', '"', '');
    }

    fclose($handle);

    return $path;
}

it('fills descriptions from the export, matched by strava id', function () {
    $run = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '111', 'description' => null]);
    $ride = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '222', 'description' => null]);

    $path = oldSiteExport([
        ['strava_id' => '111', 'description' => '  Felt amazing today  '],
        ['strava_id' => '222', 'description' => null], // no description in export
    ]);

    $this->artisan('import:activity-descriptions', ['file' => $path])->assertSuccessful();

    expect($run->refresh()->description)->toBe('Felt amazing today') // trimmed
        ->and($ride->refresh()->description)->toBeNull();             // null in export, untouched

    unlink($path);
});

it('does not clobber an existing description without --overwrite', function () {
    $activity = Activity::factory()->create([
        'platform_type' => 'strava', 'platform_id' => '111', 'description' => 'Hand-written note',
    ]);

    $path = oldSiteExport([['strava_id' => '111', 'description' => 'From the export']]);

    $this->artisan('import:activity-descriptions', ['file' => $path])->assertSuccessful();
    expect($activity->refresh()->description)->toBe('Hand-written note');

    $this->artisan('import:activity-descriptions', ['file' => $path, '--overwrite' => true])->assertSuccessful();
    expect($activity->refresh()->description)->toBe('From the export');

    unlink($path);
});

it('ignores export rows with no matching activity', function () {
    $path = oldSiteExport([['strava_id' => '999', 'description' => 'Orphan note']]);

    $this->artisan('import:activity-descriptions', ['file' => $path])
        ->expectsOutputToContain('Set descriptions on 0 activity(ies)')
        ->assertSuccessful();

    unlink($path);
});

it('fails cleanly when the file is missing', function () {
    $this->artisan('import:activity-descriptions', ['file' => '/no/such/file.csv'])
        ->expectsOutputToContain('File not found')
        ->assertFailed();
});
