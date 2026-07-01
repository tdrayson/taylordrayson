<?php

use App\Models\Activity;
use App\Models\Podcast;

it('exports a table to csv with fillable headers and the renamed video column', function () {
    Podcast::factory()->create([
        'season_number' => 7,
        'episode_number' => 250,
        'video_url' => 'https://youtu.be/test123',
    ]);

    $path = storage_path('app/export-test-podcasts.csv');
    $this->artisan('export:csv', ['file' => $path, 'type' => 'podcast'])->assertSuccessful();

    $contents = file_get_contents($path);
    unlink($path);

    expect($contents)->toContain('video_url')
        ->not->toContain('youtube_url')
        ->toContain('https://youtu.be/test123');
});

it('round-trips a json column through export then import', function () {
    Activity::factory()->create(['name' => 'Export Run', 'meta' => ['foo' => 'bar']]);

    $path = storage_path('app/export-test-activities.csv');
    $this->artisan('export:csv', ['file' => $path, 'type' => 'activity'])->assertSuccessful();

    Activity::query()->delete();
    $this->artisan('import:csv', ['file' => $path, 'type' => 'activity'])->assertSuccessful();
    unlink($path);

    $activity = Activity::first();

    expect($activity->name)->toBe('Export Run');
    expect($activity->meta)->toBe(['foo' => 'bar']);
});

it('fails for an unknown export type', function () {
    $this->artisan('export:csv', ['file' => storage_path('app/unused.csv'), 'type' => 'nope'])->assertFailed();
});
