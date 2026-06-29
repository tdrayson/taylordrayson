<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Cp\DashboardController;
use App\Http\Controllers\Cp\LoginController;
use App\Http\Controllers\Cp\ResourceController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\FeedsController;
use App\Http\Controllers\NowController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SnakeScoreController;
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

Route::get('/cp/login', [LoginController::class, 'create'])->name('cp.login');
Route::post('/cp/login', [LoginController::class, 'store'])->name('cp.login.store');
Route::post('/cp/logout', [LoginController::class, 'destroy'])->name('cp.logout');

Route::middleware('auth')->prefix('cp')->name('cp.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/{resource}/create', [ResourceController::class, 'create'])->name('resource.create');
    Route::get('/{resource}', [ResourceController::class, 'index'])->name('resource.index');
    Route::post('/{resource}', [ResourceController::class, 'store'])->name('resource.store');
    Route::get('/{resource}/{id}/edit', [ResourceController::class, 'edit'])->where('id', '[0-9]+')->name('resource.edit');
    Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->where('id', '[0-9]+')->name('resource.update');
    Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->where('id', '[0-9]+')->name('resource.destroy');
});

// CP-managed content pages, matched last so every real route wins. Letter-first
// so the digit-constrained /{year} routes are never shadowed.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z][a-z0-9-]*')->name('page');
