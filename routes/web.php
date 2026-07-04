<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedsController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\NowController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SnakeScoreController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TimelineController;
use App\Models\LeaderboardEntry;
use App\Support\OgMeta;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::feeds();
Route::get('/feeds', [FeedsController::class, 'index'])->name('feeds');
Route::get('/og.png', [OgImageController::class, 'show'])->middleware('throttle:60,1')->name('og');
Route::get('/og/entry/{entry}.png', [OgImageController::class, 'entry'])
    ->where('entry', '[0-9]+')->middleware('throttle:120,1')->name('og.entry');
// TEMP: per-type OG card preview gallery.
Route::get('/og-gallery', [OgImageController::class, 'gallery']);
Route::get('/og/preview/{type}.png', [OgImageController::class, 'preview'])
    ->where('type', '[a-z]+')->middleware('throttle:120,1');

Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::post('/search', [SearchController::class, 'index']);
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

Route::get('/photos', [GalleryController::class, 'index'])->name('photos');

Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
Route::get('/stories/{story}', [StoryController::class, 'show'])->name('stories.show');

Route::get('/now', [NowController::class, 'index'])->name('now');
Route::get('/design-system', fn () => Inertia::render('DesignSystem', [
    'og' => OgMeta::designSystem(),
]))->name('design-system');

Route::get('/sleep-score', fn () => Inertia::render('SleepScore', [
    'og' => ['title' => 'How the sleep score works'],
]))->name('sleep-score');

// 404 snake leaderboard: a fresh single-use token per game, then the score post.
Route::post('/snake/token', [SnakeScoreController::class, 'token'])
    ->middleware('throttle:30,1')->name('snake.token');
Route::post('/snake/score', [SnakeScoreController::class, 'store'])
    ->middleware('throttle:10,1')->name('snake.score');
Route::post('/snake/rename', [SnakeScoreController::class, 'rename'])
    ->middleware('throttle:10,1')->name('snake.rename');
Route::get('/leaderboard', fn () => Inertia::render('Leaderboard', [
    'og' => OgMeta::leaderboard(),
    'entries' => LeaderboardEntry::topEntries(null),
]))->name('leaderboard');

// Per-type archive pages and their taxonomy sub-routes. Slugs are literal segments,
// so they never collide with the digit-constrained /{year}/... routes below.
foreach (TypeRegistry::all() as $type => $definition) {
    Route::get($definition['slug'], [ArchiveController::class, 'index'])
        ->defaults('type', $type)->name("archive.{$definition['slug']}");

    if ($taxonomy = $definition['taxonomy']) {
        Route::get($taxonomy['base'].'/{value}', [ArchiveController::class, 'taxonomy'])
            ->defaults('type', $type)->name("archive.{$taxonomy['base']}");
    }
}

Route::get('/', [TimelineController::class, 'index'])->name('timeline');
Route::get('/{year}', [TimelineController::class, 'year'])
    ->where(['year' => '\d{4}'])->name('year');
Route::get('/{year}/{month}', [TimelineController::class, 'month'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}'])->name('month');
Route::get('/{year}/{month}/{day}', [TimelineController::class, 'day'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}'])->name('day');
Route::get('/{year}/{month}/{day}/{slug}', [EntryController::class, 'show'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}'])->name('entry');

// Cross-type tag feed. Registered above the page catch-all so /tags/{slug}
// never falls through to PageController.
Route::get('/tags/{slug}', [TagController::class, 'show'])->name('tags.show');

// Content pages, matched last so every real route wins. Letter-first so the
// digit-constrained /{year} routes are never shadowed.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z][a-z0-9-]*')->name('page');
