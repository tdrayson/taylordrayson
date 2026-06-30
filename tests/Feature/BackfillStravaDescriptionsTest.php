<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.strava.client_id' => 'cid',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);
    Cache::flush();
});

/** Fake the OAuth token plus a detail response per activity id. */
function fakeStravaDetails(array $descriptionsById): void
{
    $responses = ['*/oauth/token*' => Http::response(['access_token' => 'token'])];

    foreach ($descriptionsById as $id => $description) {
        $responses["*/activities/{$id}*"] = Http::response(['id' => $id, 'description' => $description]);
    }

    Http::fake($responses);
}

it('backfills descriptions and leaves blank ones null', function () {
    $withNote = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '100', 'description' => null]);
    $blank = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '200', 'description' => null]);

    fakeStravaDetails([
        100 => '  Felt strong today  ',
        200 => '',
    ]);

    $this->artisan('strava:backfill-descriptions')->assertSuccessful();

    expect($withNote->refresh()->description)->toBe('Felt strong today') // trimmed
        ->and($blank->refresh()->description)->toBeNull();               // blank stays null
});

it('does not touch non-strava activities', function () {
    $health = Activity::factory()->create(['platform_type' => 'health', 'platform_id' => null, 'description' => null]);

    fakeStravaDetails([]);

    $this->artisan('strava:backfill-descriptions')
        ->expectsOutputToContain('Nothing left to backfill.')
        ->assertSuccessful();

    expect($health->refresh()->description)->toBeNull();
});

it('resumes from the cursor across runs', function () {
    $first = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '100', 'description' => null]);
    $second = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '200', 'description' => null]);

    fakeStravaDetails([100 => 'first', 200 => 'second']);

    // One per run: the first run handles the oldest, the second resumes.
    $this->artisan('strava:backfill-descriptions', ['--limit' => 1])->assertSuccessful();

    expect($first->refresh()->description)->toBe('first')
        ->and($second->refresh()->description)->toBeNull();

    $this->artisan('strava:backfill-descriptions', ['--limit' => 1])->assertSuccessful();

    expect($second->refresh()->description)->toBe('second');

    // A third run has nothing left to do.
    $this->artisan('strava:backfill-descriptions', ['--limit' => 1])
        ->expectsOutputToContain('Nothing left to backfill.')
        ->assertSuccessful();
});

it('stops after consecutive failures without advancing the cursor past them', function () {
    foreach (range(1, 6) as $i) {
        Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => (string) ($i * 10), 'description' => null]);
    }

    // Token succeeds, every detail call fails (simulating a 429 storm).
    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/activities/*' => Http::response([], 429),
    ]);

    $this->artisan('strava:backfill-descriptions')->assertSuccessful();

    // Nothing got a description, and the cursor was rolled back so a re-run retries.
    expect(Activity::whereNotNull('description')->count())->toBe(0)
        ->and((int) Cache::get('strava:desc-backfill:cursor', 0))->toBe(0);
});

it('skips activities already in the export and fetches only new ones', function () {
    $old = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '100', 'description' => null]);
    $new = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '200', 'description' => null]);

    // Old-site export shape: id 100 is covered, id 200 is not.
    $exportPath = sys_get_temp_dir().'/export_'.uniqid().'.csv';
    $handle = fopen($exportPath, 'w');
    fputcsv($handle, ['ID', 'Title', 'Activity data', 'Content'], ',', '"', '');
    fputcsv($handle, ['1', 'Old', json_encode(['Activity ID' => '100', 'Description' => 'old']), '.'], ',', '"', '');
    fclose($handle);

    fakeStravaDetails([100 => 'old', 200 => 'brand new']);

    $this->artisan('strava:backfill-descriptions', ['--skip-export' => $exportPath])->assertSuccessful();

    expect($old->refresh()->description)->toBeNull()           // in the export, left untouched
        ->and($new->refresh()->description)->toBe('brand new'); // not in the export, fetched

    unlink($exportPath);
});

it('leaves activities that already have a description untouched', function () {
    $described = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '100', 'description' => 'Imported note']);
    $blank = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '200', 'description' => null]);

    // If 100 were fetched it would be overwritten with this; it must not be.
    fakeStravaDetails([100 => 'fresh from strava', 200 => 'newly fetched']);

    $this->artisan('strava:backfill-descriptions')->assertSuccessful();

    expect($described->refresh()->description)->toBe('Imported note') // not clobbered
        ->and($blank->refresh()->description)->toBe('newly fetched'); // still backfilled
});

it('restarts from the beginning when --restart is passed', function () {
    Cache::put('strava:desc-backfill:cursor', 99999);

    $activity = Activity::factory()->create(['platform_type' => 'strava', 'platform_id' => '100', 'description' => null]);

    fakeStravaDetails([100 => 'recovered']);

    $this->artisan('strava:backfill-descriptions', ['--restart' => true])->assertSuccessful();

    expect($activity->refresh()->description)->toBe('recovered');
});
