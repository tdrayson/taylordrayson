<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\SnakeScoreController;
use App\Http\Controllers\TimelineController;
use App\Models\LeaderboardEntry;
use App\Timeline\TypeRegistry;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::feeds();

Route::get('/now', fn () => Inertia::render('Now'))->name('now');
Route::get('/design-system', fn () => Inertia::render('DesignSystem'))->name('design-system');

// 404 snake leaderboard: a fresh single-use token per game, then the score post.
Route::post('/snake/token', [SnakeScoreController::class, 'token'])
    ->middleware('throttle:30,1')->name('snake.token');
Route::post('/snake/score', [SnakeScoreController::class, 'store'])
    ->middleware('throttle:10,1')->name('snake.score');
Route::post('/snake/rename', [SnakeScoreController::class, 'rename'])
    ->middleware('throttle:10,1')->name('snake.rename');
Route::get('/leaderboard', fn () => Inertia::render('Leaderboard', [
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
