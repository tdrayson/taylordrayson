<?php

use Illuminate\Support\Facades\Schedule;

// Capture: everything that pulls from a third party. Each is incremental and
// widens its window to cover a missed run, which is what makes them safe to run
// often. `withoutOverlapping()` throughout, so a slow run never stacks.
// Push-based capture (Health Auto Export, Setgraph) is set on the phone instead.

// Watch history is the most time-sensitive capture here, but it arrives in
// evening bursts: every minute spent 2,880 requests a day to shave minutes off
// an entry appearing. Ratings page the whole library, so they stay daily.
Schedule::command('trakt:sync --days=1 --skip-ratings')->everyTenMinutes()->withoutOverlapping();
Schedule::command('trakt:sync --ratings-only')->dailyAt('04:10')->withoutOverlapping();

// Strava fetches the polyline and photos inline, so an activity is complete
// on arrival apart from its charts (see the enrichment block below). It also
// compares each summary against the row it already has, so a title rewritten
// or photos added after Strava auto-published arrive on the next run.
Schedule::command('strava:sync --days=2')->everyFiveMinutes()->withoutOverlapping();

// A description written on its own leaves the summary identical, so nothing
// above can see it. This asks Strava outright, which costs one request per
// activity in the last two days: a handful, and only once an hour.
Schedule::command('strava:sync --days=2 --refresh')->hourly()->withoutOverlapping();

// Keep the recent food diary fresh in near real time, re-checking the last few
// days so food logged late for an earlier day is picked up.
Schedule::command('rovi:sync-food')->everyFifteenMinutes()->withoutOverlapping();

// Today's step count for the status bar. The odd one out above: it stores no
// history, so there is no gap to heal and it asks only for today, whose total
// climbs until midnight and is simply refetched.
Schedule::command('rovi:sync-steps')->everyFifteenMinutes()->withoutOverlapping();

// Swarm check-ins, asking only for what postdates the newest stored one.
Schedule::command('foursquare:sync')->everyTenMinutes()->withoutOverlapping();

// Episodes publish weekly, so once a day is ample. It used to run every half
// hour, and because the sync re-fetches all 43 pages each time (see #85), that
// read as scraping to the podcast site's WAF and got this server's IP blocked.
Schedule::command('podcast:sync')->dailyAt('05:20')->withoutOverlapping();

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

// OG cards rendered from a design that has since changed. Nothing points at
// them, and the current generation is kept, so this never forces a re-render.
Schedule::command('og:clear --stale')->weeklyOn(1, '04:00')->withoutOverlapping();

// Backups: the database as versioned archives, the assets as a single mirror.
// They have different shapes, so they are kept apart: every backup:run is a
// full zip with no deduplication, and media files are written once and never
// modified, so archiving them would keep several copies of the same images.

// All four are held back until R2 is configured. Without credentials the
// backup commands throw while building the destination, which would be a
// stack trace and an alert twice a day rather than a useful signal.
$mirrorReady = fn (): bool => filled(config('filesystems.disks.r2.bucket'));

// ~8MB compressed. Twice daily is ample: most of the data re-derives from the
// syncs, and what does not (notes, articles) changes rarely.
Schedule::command('backup:run --only-db')->twiceDaily(3, 15)->when($mirrorReady)->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('03:10')->when($mirrorReady)->withoutOverlapping();

// Reports an unhealthy destination by exiting non-zero, which the scheduler's
// failure listener turns into an alert. That covers the case the archives
// cannot: a backup that never ran at all.
Schedule::command('backup:monitor')->dailyAt('09:00')->when($mirrorReady);

// Originals only; conversions and responsive images rebuild from them.
Schedule::command('assets:mirror')->dailyAt('04:40')->when($mirrorReady)->withoutOverlapping();

// Cached faces and favicons, refreshed on a slow cycle. Both are written once
// when the thing that needs them arrives and never revisited, so a changed
// avatar or a rebranded site keeps its old image indefinitely, and a directory
// lost to a deploy stays lost. Monthly is enough for both: they are cosmetic,
// and each run re-downloads every file, which is not something to do often.
Schedule::command('webmentions:avatars')->monthlyOn(1, '04:20')->withoutOverlapping();
Schedule::command('links:favicons --force')->monthlyOn(1, '04:50')->withoutOverlapping();
