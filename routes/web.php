<?php

use App\Enums\ExportFormat;
use App\Enums\LinkPage;
use App\Http\Controllers\AuthoringController;
use App\Http\Controllers\CaloriesRedirectController;
use App\Http\Controllers\CitationPreviewController;
use App\Http\Controllers\ClearFailedJobsController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\EntryExportController;
use App\Http\Controllers\FeedsController;
use App\Http\Controllers\FlightMapController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LinkPageContactController;
use App\Http\Controllers\LinkPageController;
use App\Http\Controllers\LinkPageDetailsController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MarkResponseMineController;
use App\Http\Controllers\MediaUploadController;
use App\Http\Controllers\MentionSearchController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\MoreController;
use App\Http\Controllers\NowController;
use App\Http\Controllers\NowExportController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PageExportController;
use App\Http\Controllers\RandomEntryController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\RetryFailedJobsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SnakeScoreController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\SyncDatasetController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TvShowController;
use App\Http\Controllers\UnlockEntryController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\UpdateEntryStatusController;
use App\Http\Controllers\WebmentionController;
use App\Http\Middleware\NoIndex;
use Illuminate\Support\Facades\Route;

// Link-in-bio cards on their own subdomain, first so no main-site route claims
// these paths there. The trailing catch-all keeps the rest of the site off it.
Route::domain(config('profile.domain'))->middleware(NoIndex::class)->group(function (): void {
    Route::redirect('/', '/'.LinkPage::Personal->value);
    Route::get('/{page}', LinkPageController::class)->name('link-page.show');
    Route::get('/{page}/details', LinkPageDetailsController::class)->name('link-page.details');
    // No .vcf extension: nginx serves static-looking extensions itself.
    Route::get('/{page}/contact', LinkPageContactController::class)->name('link-page.contact');
    Route::get('/{any}', fn () => abort(404))->where('any', '.*');
});

// Sign-in, required first: the /{slug} page catch-all at the bottom matches
// any lowercase word, 'login' included, so registering it later would let a
// content page shadow the login form.
require __DIR__.'/auth.php';

// Body media is stored root-relative, so locally a file nginx cannot find
// falls through to wherever MEDIA_URL points.
if (app()->isLocal() && str_starts_with(config('filesystems.disks.public.url'), 'http')) {
    Route::redirect('/storage/{path}', config('filesystems.disks.public.url').'/{path}')->where('path', '.*');
}

// Authoring, session-guarded: the only caller is the editor in a signed-in
// browser. Above the /{slug} catch-all for the same reason as /login.
Route::middleware('auth')->group(function (): void {
    Route::get('/mentions/search', MentionSearchController::class)->name('mentions.search');
    Route::post('/citations/preview', CitationPreviewController::class)
        ->middleware('throttle:30,1')
        ->name('citations.preview');

    // Quick-add hub, then one form per type. Both above the /{slug} catch-all.
    Route::get('/new', [AuthoringController::class, 'new'])->name('new');
    Route::get('/new/{type}', [AuthoringController::class, 'new'])->name('new.type');
    Route::post('/entries/{type}', [AuthoringController::class, 'store'])->name('entries.store');
    Route::patch('/entries/{type}/{id}', [AuthoringController::class, 'update'])
        ->where('id', '[0-9]+')->name('entries.update');

    // Owner-only status change, valid for every dataset with HasStatus (synced types included).
    Route::patch('/entries/{dataset}/{id}/status', UpdateEntryStatusController::class)
        ->where(['dataset' => '[a-z-]+', 'id' => '[0-9]+'])->name('entries.status');

    // Autocomplete for the fields that cannot be a plain text box.
    // Hyphens included: `fuel-brand` is a source name and 404s without them.
    Route::get('/lookup/{source}', LookupController::class)
        ->where('source', '[a-z-]+')->name('lookup');
    Route::get('/lookup-reverse', [LookupController::class, 'reverse'])->name('lookup.reverse');

    // Drafts have no timeline entry, so they appear in no listing without this.
    Route::get('/drafts', [AuthoringController::class, 'drafts'])->name('drafts');
    Route::get('/drafts/{dataset}/{id}', [EntryController::class, 'draft'])
        ->where(['dataset' => '[a-z-]+', 'id' => '[0-9]+'])->name('drafts.show');

    // The back-of-house overview: what needs a decision, who has responded, and
    // whether the syncs are still arriving. A lowercase word, so it has to sit
    // above the /{slug} catch-all or a content page could shadow it.
    Route::get('/hq', HubController::class)->name('hq');
    Route::post('/hq/failed-jobs/retry', RetryFailedJobsController::class)
        ->middleware('throttle:10,1')->name('hq.failed-jobs.retry');
    Route::post('/hq/failed-jobs/clear', ClearFailedJobsController::class)
        ->middleware('throttle:10,1')->name('hq.failed-jobs.clear');
    Route::post('/hq/sync/{dataset}', SyncDatasetController::class)
        ->where('dataset', '[a-z-]+')->middleware('throttle:10,1')->name('hq.sync');

    // A Strava or Swarm reply of mine, marked by hand where the source cannot say.
    Route::patch('/responses/syndicated/{response}/mine', MarkResponseMineController::class)
        ->whereNumber('response')->name('responses.mine');

    // The queue for held comments and mentions, for now still its own page.
    Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation');
    Route::post('/moderation/{kind}/{id}/{action}', [ModerationController::class, 'update'])
        ->where(['kind' => 'comment|mention', 'id' => '[0-9]+', 'action' => 'approve|spam|delete'])
        ->name('moderation.update');

    Route::post('/media/pending', [MediaUploadController::class, 'store'])->name('media.pending.store');
    Route::get('/media/pending/{token}', [MediaUploadController::class, 'show'])->name('media.pending.show');

});

// Feeds
Route::feeds();
Route::get('/feeds', [FeedsController::class, 'index'])->name('feeds');

// Crawler files. robots.txt is a route rather than a file in public/ because
// the server rewrites the path to index.php, so a static file is never reached.
Route::get('/robots.txt', fn () => response()
    ->view('robots')
    ->header('Content-Type', 'text/plain'))->name('robots');

// The PWA manifest, a route rather than a file in public/ so its icon URLs can
// carry a content hash. public/manifest.webmanifest has to stay deleted: the
// server serves an existing file before it falls through to the app.
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap/pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap/{year}.xml', [SitemapController::class, 'year'])
    ->where('year', '\d{4}')->name('sitemap.year');

// Directory
Route::get('/more', MoreController::class)->name('more');

// OG images
Route::get('/og.png', [OgImageController::class, 'show'])->middleware('throttle:60,1')->name('og');
Route::get('/og/entry/{entry}.png', [OgImageController::class, 'entry'])
    ->where('entry', '[0-9]+')->middleware('throttle:120,1')->name('og.entry');
// TEMP: per-type OG card preview gallery.
Route::get('/og-gallery', [OgImageController::class, 'gallery']);
Route::get('/og/preview/{type}.png', [OgImageController::class, 'preview'])
    ->where('type', '[a-z-]+')->middleware('throttle:120,1');

// Search
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::post('/search', [SearchController::class, 'index']);
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

// Unlocking a private entry or page. Tight throttle: this is a password guess.
Route::post('/unlock/{dataset}/{id}', UnlockEntryController::class)
    ->where(['dataset' => '[a-z-]+', 'id' => '[0-9]+'])->middleware('throttle:5,1')->name('unlock');

// Photos
Route::get('/photos', [GalleryController::class, 'index'])->name('photos');

// Stories
Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
Route::get('/stories/{story}', [StoryController::class, 'show'])->name('stories.show');

// Now
Route::get('/now', [NowController::class, 'index'])->name('now');

// /now's export, registered directly above it and above the page catch-all:
// a content page slugged "now" would otherwise shadow this route.
Route::get('/now.{format}', NowExportController::class)
    ->where('format', ExportFormat::pattern())->name('now.export');

// Standalone Inertia pages
Route::get('/design-system', DesignSystemController::class)->name('design-system');
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');

// Reached only from a link in a reply notification, so it is signed rather
// than guarded: the signature is the proof, and there is no account to log in to.
Route::get('/unsubscribe/{comment}', UnsubscribeController::class)
    ->where('comment', '[0-9]+')->middleware('signed')->name('unsubscribe');

// The public webmention endpoint. Discovery points here from every page, so
// the URL is part of the site's contract and must not move.
Route::post('/webmention', WebmentionController::class)
    ->middleware('throttle:60,1')->name('webmention');

// Comments: a token when the form is first touched, then the comment itself.
Route::post('/comments/token', [CommentController::class, 'token'])
    ->middleware('throttle:20,1')->name('comments.token');
Route::post('/comments/{type}/{id}', [CommentController::class, 'store'])
    ->where(['type' => '[a-z][a-z0-9-]*', 'id' => '[0-9]+'])
    ->middleware('throttle:5,10')->name('comments.store');

// Reactions. The type/id pair is resolved against an allowlist, so this is not
// a handle on every model in the app. The browser picks its own identity, so
// the hourly throttle is the only brake on stuffing a count.
Route::post('/reactions/{type}/{id}', [ReactionController::class, 'store'])
    ->where(['type' => '[a-z][a-z0-9-]*', 'id' => '[0-9]+'])
    ->middleware('throttle:30,60')->name('reactions.store');

// 404 snake leaderboard: a fresh single-use token per game, then the score post.
Route::post('/snake/token', [SnakeScoreController::class, 'token'])
    ->middleware('throttle:30,1')->name('snake.token');
Route::post('/snake/score', [SnakeScoreController::class, 'store'])
    ->middleware('throttle:10,1')->name('snake.score');
Route::post('/snake/rename', [SnakeScoreController::class, 'rename'])
    ->middleware('throttle:10,1')->name('snake.rename');

// TV show pages, registered above the generic archive loop for the same
// reason as /flights/map below: a literal segment above the taxonomy routes.
Route::get('/tv-shows', [TvShowController::class, 'index'])->name('tv-shows.index');
Route::get('/tv-shows/{tvShow:slug}', [TvShowController::class, 'show'])->name('tv-shows.show');

// Old show urls, a pattern redirect config/redirects.php cannot express.
Route::redirect('/media/tv/{slug}', '/tv-shows/{slug}', 301);

// Literal segment must beat the archive taxonomy route (/flights/{value}).
Route::get('/flights/map', FlightMapController::class)->name('flights.map');

// Per-type archive pages and their taxonomy sub-routes, registered by the
// Route::archives() macro. Slugs are literal segments, so they never collide
// with the digit-constrained /{year}/... routes below.
Route::archives();

// Stats
Route::get('/stats/{type}', [StatsController::class, 'show'])
    ->where('type', '[a-z][a-z0-9-]*')->name('stats.show');

// Timeline: home, on-this-day, lucky/random, then digit-constrained dated routes
// and the entry page.
Route::get('/', [TimelineController::class, 'index'])->name('timeline');
Route::get('/on-this-day', [TimelineController::class, 'onThisDay'])->name('on-this-day');
Route::get('/lucky', RandomEntryController::class)->name('lucky');
Route::get('/random', RandomEntryController::class)->name('random');
Route::get('/{year}', [TimelineController::class, 'year'])
    ->where(['year' => '\d{4}'])->name('year');
Route::get('/{year}/{month}', [TimelineController::class, 'month'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}'])->name('month');
Route::get('/{year}/{month}/{day}', [TimelineController::class, 'day'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}'])->name('day');

// Old food day slug, a literal segment above the entry route below so it
// never shadows another dated entry.
Route::get('/{year}/{month}/{day}/calories', CaloriesRedirectController::class)
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}'])->name('calories.redirect');

// Entry exports. Above the entry route, whose unconstrained {slug} would
// otherwise swallow "krk-lgw.json" whole and 404 on it.
Route::get('/{year}/{month}/{day}/{slug}.{format}', EntryExportController::class)
    ->where([
        'year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}',
        'format' => ExportFormat::pattern(),
    ])->name('entry.export');
Route::get('/{year}/{month}/{day}/{slug}', [EntryController::class, 'show'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}'])->name('entry');

// Tag index + cross-type tag feed. The literal /tags is registered before the
// /tags/{slug} feed, and both above the page catch-all so neither falls through
// to PageController.
Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
Route::get('/tags/{slug}', [TagController::class, 'show'])->name('tags.show');

// Trips: a named date range whose page gathers the entries already inside it.
// Registered above the page catch-all for the same reason as /tags.
Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
Route::get('/trips/{slug}', [TripController::class, 'show'])->name('trips.show');

// Old site URLs, exact-match only so a live sub-route is never shadowed.
foreach (config('redirects') as $from => $to) {
    Route::redirect("/{$from}", "/{$to}", 301);
}

// Page exports, above the page catch-all for the same reason the entry export
// sits above the entry route.
Route::get('/{slug}.{format}', PageExportController::class)
    ->where(['slug' => '[a-z][a-z0-9-]*', 'format' => ExportFormat::pattern()])->name('page.export');

// Content pages, matched last so every real route wins. Letter-first so the
// digit-constrained /{year} routes are never shadowed.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z][a-z0-9-]*')->name('page');
