<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Capture
|--------------------------------------------------------------------------
|
| Everything that pulls from a third party. Each command is incremental and
| self-healing: it asks only for what is newer than the last stored row, and
| widens its window to cover a missed run rather than stranding the gap. That
| is what makes these safe to run often, and why `withoutOverlapping()` is on
| all of them: a slow run must never stack on the next tick.
|
| Push-based capture is not here by design. Health Auto Export and Setgraph
| POST to /api/v1, so their freshness is set on the phone, not by this file.
|
*/

// Watch history is what should appear quickest, and a run with nothing new is
// a single empty page per type. Ratings are excluded here because they page
// the whole ratings library on every call; they get their own daily pass.
Schedule::command('trakt:sync --days=1 --skip-ratings')->everyMinute()->withoutOverlapping();
Schedule::command('trakt:sync --ratings-only')->dailyAt('04:10')->withoutOverlapping();

// Strava fetches the polyline and photos inline, so an activity is complete
// on arrival apart from its charts (see the enrichment block below).
Schedule::command('strava:sync --days=2')->everyFiveMinutes()->withoutOverlapping();

// Keep the recent food diary fresh in near real time, re-checking the last few
// days so food logged late for an earlier day is picked up.
Schedule::command('rovi:sync-food')->everyFifteenMinutes()->withoutOverlapping();

// Swarm check-ins, asking only for what postdates the newest stored one.
Schedule::command('foursquare:sync')->everyTenMinutes()->withoutOverlapping();

// Podcasts re-fetch the full episode list on every run (see issue #85), so
// this stays daily until it is made incremental like the others.
Schedule::command('podcast:sync')->dailyAt('05:00')->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Enrichment
|--------------------------------------------------------------------------
|
| Derived work for rows the capture pass has already stored. All of these skip
| what is already done, so they are cheap when there is nothing new and they
| double as a repair pass for anything a failed run left half-finished.
|
*/

// Activity streams (the heart-rate, elevation and speed charts) are the one
// part of an activity that strava:sync does not fetch inline.
Schedule::command('strava:streams')->hourly()->withoutOverlapping();

// Static timeline maps for newly located entries of each mappable type.
foreach (['activity', 'flight', 'fuel', 'checkin'] as $mappableType) {
    Schedule::command("maps:generate {$mappableType}")->hourly()->withoutOverlapping();
}
