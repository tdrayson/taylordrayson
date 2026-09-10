<?php

use App\Http\Controllers\AuthoringController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\DynamicTagPreviewController;
use App\Http\Controllers\DynamicTagsController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedsController;
use App\Http\Controllers\FlightMapController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MediaUploadController;
use App\Http\Controllers\MentionSearchController;
use App\Http\Controllers\MoreController;
use App\Http\Controllers\NowController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RandomEntryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SnakeScoreController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

// Sign-in, required first: the /{slug} page catch-all at the bottom matches
// any lowercase word, 'login' included, so registering it later would let a
// content page shadow the login form.
require __DIR__.'/auth.php';

// Authoring, session-guarded: the only caller is the editor in a signed-in
// browser. Above the /{slug} catch-all for the same reason as /login.
Route::middleware('auth')->group(function (): void {
    Route::get('/mentions/search', MentionSearchController::class)->name('mentions.search');
    Route::get('/dynamic-tags', DynamicTagsController::class)->name('dynamic-tags');
    // Resolves a tag against options an author is still choosing, rather than
    // only ever the defaults the list above carries.
    Route::get('/dynamic-tags/preview', DynamicTagPreviewController::class)->name('dynamic-tags.preview');

    // Quick-add hub, then one form per type. Both above the /{slug} catch-all.
    Route::get('/new', [AuthoringController::class, 'new'])->name('new');
    Route::get('/new/{type}', [AuthoringController::class, 'new'])->name('new.type');
    Route::post('/entries/{type}', [AuthoringController::class, 'store'])->name('entries.store');
    Route::patch('/entries/{type}/{id}', [AuthoringController::class, 'update'])
        ->where('id', '[0-9]+')->name('entries.update');

    // Autocomplete for the fields that cannot be a plain text box.
    // Hyphens included: `fuel-brand` is a source name and 404s without them.
    Route::get('/lookup/{source}', LookupController::class)
        ->where('source', '[a-z-]+')->name('lookup');
    Route::get('/lookup-reverse', [LookupController::class, 'reverse'])->name('lookup.reverse');

    // Drafts have no timeline entry, so they appear in no listing without this.
    Route::get('/drafts', [AuthoringController::class, 'drafts'])->name('drafts');

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
    ->where('type', '[a-z]+')->middleware('throttle:120,1');

// Search
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::post('/search', [SearchController::class, 'index']);
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

// Photos
Route::get('/photos', [GalleryController::class, 'index'])->name('photos');

// Stories
Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
Route::get('/stories/{story}', [StoryController::class, 'show'])->name('stories.show');

// Now
Route::get('/now', [NowController::class, 'index'])->name('now');

// Standalone Inertia pages
Route::get('/design-system', DesignSystemController::class)->name('design-system');
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');

// 404 snake leaderboard: a fresh single-use token per game, then the score post.
Route::post('/snake/token', [SnakeScoreController::class, 'token'])
    ->middleware('throttle:30,1')->name('snake.token');
Route::post('/snake/score', [SnakeScoreController::class, 'store'])
    ->middleware('throttle:10,1')->name('snake.score');
Route::post('/snake/rename', [SnakeScoreController::class, 'rename'])
    ->middleware('throttle:10,1')->name('snake.rename');

// TV show pages, registered above the generic archive/taxonomy loop so
// /media/tv wins over the /media/{value} taxonomy route for the 'tv' value.
Route::get('/media/tv', [SeriesController::class, 'index'])->name('series.index');
Route::get('/media/tv/{series:slug}', [SeriesController::class, 'show'])->name('series.show');

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

// Content pages, matched last so every real route wins. Letter-first so the
// digit-constrained /{year} routes are never shadowed.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z][a-z0-9-]*')->name('page');
