<?php

use Illuminate\Support\Facades\Schedule;

// Capture: everything that pulls from a third party. Each is incremental and
// widens its window to cover a missed run, which is what makes them safe to run
// often. `withoutOverlapping()` throughout, so a slow run never stacks.
// Push-based capture (Health Auto Export, Setgraph) is set on the phone instead.

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

// Today's step count for the status bar. The odd one out above: it stores no
// history, so there is no gap to heal and it asks only for today, whose total
// climbs until midnight and is simply refetched.
Schedule::command('rovi:sync-steps')->everyFifteenMinutes()->withoutOverlapping();

// Swarm check-ins, asking only for what postdates the newest stored one.
Schedule::command('foursquare:sync')->everyTenMinutes()->withoutOverlapping();

// Episodes publish weekly, but the feed is the only signal that one is out, so
// this polls often enough that a new episode is up within the half hour. A run
// with nothing new is one page and five upserts.
Schedule::command('podcast:sync')->everyThirtyMinutes()->withoutOverlapping();

// Enrichment: derived work for rows capture has already stored. All skip what is
// done, so they are cheap when idle and double as a repair pass.

// Activity streams (the heart-rate, elevation and speed charts) are the one
// part of an activity that strava:sync does not fetch inline.
Schedule::command('strava:streams')->hourly()->withoutOverlapping();

// Static timeline maps for newly located entries of each mappable type.
Schedule::command('maps:generate flight')->hourly()->withoutOverlapping();
Schedule::command('maps:generate checkin')->hourly()->withoutOverlapping();
Schedule::command('maps:generate fuel')->hourly()->withoutOverlapping();
Schedule::command('maps:generate activity')->hourly()->withoutOverlapping();

// Housekeeping: storage the app has stopped referencing but never removes on its
// own. Both only delete, so a missed run costs disk rather than data.

// Editor uploads abandoned before the entry was saved.
Schedule::command('media:prune-pending')->dailyAt('03:40');

// Files left behind by deleted attachments, plus conversions no longer declared.
Schedule::command('media-library:clean --force')->weeklyOn(1, '03:50')->withoutOverlapping();
