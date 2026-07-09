<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Http;

it('backfills timezone and local occurred_at on existing activities', function () {
    Activity::factory()->create([
        'source' => 'strava',
        'source_id' => '12345',
        'occurred_at' => '2018-02-20 18:02:13', // stored UTC originally
        'timezone' => null,
    ]);

    Http::fake([
        '*/oauth/token*' => Http::response(['access_token' => 'token']),
        '*/athlete/activities*' => Http::sequence()
            ->push([[
                'id' => 12345,
                'start_date' => '2018-02-20T18:02:13Z',
                'start_date_local' => '2018-02-20T10:02:13Z',
                'timezone' => '(GMT-08:00) America/Los_Angeles',
            ]])
            ->push([]),
    ]);

    $this->artisan('strava:backfill-timezones')->assertSuccessful();

    $activity = Activity::where('source_id', '12345')->first();
    expect($activity->timezone)->toBe('America/Los_Angeles');
    expect($activity->occurred_at->format('Y-m-d H:i:s'))->toBe('2018-02-20 10:02:13');
});
