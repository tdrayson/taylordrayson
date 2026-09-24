<?php

use App\Datasets\Datasets;
use App\Enums\TimelineType;
use Illuminate\Support\Facades\Schedule;

// Capture: everything that pulls from a third party. Each is incremental and
// widens its window to cover a missed run, which is what makes them safe to run
// often. `withoutOverlapping()` throughout, so a slow run never stacks.
// Push-based capture (Health Auto Export, Setgraph) is set on the phone instead.
// A dataset's command lives on the dataset, so HQ's Sync now runs the same one.

// Watch history is the most time-sensitive capture here, but it arrives in
// evening bursts: every minute spent 2,880 requests a day to shave minutes off
// an entry appearing. Ratings page the whole library, so they stay daily.
Schedule::command(Datasets::for(TimelineType::Film)->syncCommand())->everyTenMinutes()->withoutOverlapping();
Schedule::command('trakt:sync --ratings-only')->dailyAt('04:10')->withoutOverlapping();

// Strava pushes activity creates, edits and deletes to the webhook, so this is
// no longer how an activity is found: it is the safety net for an event that
// never arrived, and for the one edit the events do not cover. Strava's
// `updates` hash documents only title, type and private, so a description
// written on its own is not reliably pushed; --refresh asks outright, which
// costs one request per activity in the window. Twice a day caps that lag at
// twelve hours for about seven reads.
Schedule::command(Datasets::for(TimelineType::Activity)->syncCommand())->twiceDaily(4, 16)->withoutOverlapping();

// Kudos and comments left on an activity after it published. Only the
// summary counts are checked each run, so a quiet activity costs nothing.
Schedule::command('strava:responses')->hourly()->withoutOverlapping();

// Keep the recent food diary fresh in near real time, re-checking the last few
// days so food logged late for an earlier day is picked up.
Schedule::command(Datasets::for(TimelineType::Food)->syncCommand())->everyFifteenMinutes()->withoutOverlapping();

// Today's step count for the status bar. The odd one out above: it stores no
// history, so there is no gap to heal and it asks only for today, whose total
// climbs until midnight and is simply refetched.
Schedule::command('rovi:sync-steps')->everyFifteenMinutes()->withoutOverlapping();

// Swarm check-ins, asking only for what postdates the newest stored one.
Schedule::command(Datasets::for(TimelineType::Place)->syncCommand())->everyTenMinutes()->withoutOverlapping();

// Likes and comments left on check-ins after they were synced.
Schedule::command('swarm:responses')->hourly()->withoutOverlapping();

// Episodes publish weekly, so once a day is ample. It used to run every half
// hour, and because the sync re-fetches all 43 pages each time (see #85), that
// read as scraping to the podcast site's WAF and got this server's IP blocked.
Schedule::command(Datasets::for(TimelineType::ThisWeekWith)->syncCommand())->dailyAt('05:20')->withoutOverlapping();

// Enrichment: derived work for rows capture has already stored. All skip what is
// done, so they are cheap when idle and double as a repair pass.

// Activity streams are fetched as each activity is stored now, so this is the
// repair pass for an activity whose streams Strava had not finished processing
// at the time. It spends nothing at all when none are missing.
Schedule::command('strava:streams')->dailyAt('04:30')->withoutOverlapping();

// Static timeline maps for newly located entries of each mappable type.
Schedule::command('maps:generate flight')->hourly()->withoutOverlapping();
Schedule::command('maps:generate place')->hourly()->withoutOverlapping();
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
