<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TimelineController::class, 'index']);
Route::get('/{year}/{month}/{day}/{slug}', [EntryController::class, 'show'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}']);
