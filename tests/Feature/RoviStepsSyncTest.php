<?php

use App\Support\TodaySteps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Shape captured from a live /v1/me/steps response. `id` is the day; `date`
 * holds the write time and is deliberately adrift here, as it is in the real
 * feed.
 */
function fakeRoviSteps(array $rows): void
{
    Http::fake(['*/v1/me/steps*' => Http::response([
        'data' => $rows,
        'paging' => ['nextCursor' => null, 'hasMore' => false],
    ])]);
}

beforeEach(fn () => TodaySteps::forget());

it('caches todays step count', function () {
    $today = Carbon::today()->toDateString();
    fakeRoviSteps([[
        'id' => $today,
        'steps' => 2240,
        'stepCalories' => 64,
        'date' => ['_seconds' => 1785609217, '_nanoseconds' => 0],
    ]]);

    $this->artisan('rovi:sync-steps')->assertExitCode(0);

    expect(TodaySteps::get())->toBe(2240);
});

it('keys on the row id rather than the date field', function () {
    $today = Carbon::today()->toDateString();
    fakeRoviSteps([
        // A stale row whose write timestamp is today but whose day is not.
        ['id' => Carbon::today()->subDays(3)->toDateString(), 'steps' => 9999, 'date' => ['_seconds' => Carbon::now()->timestamp]],
        ['id' => $today, 'steps' => 2240, 'date' => ['_seconds' => 1785609217]],
    ]);

    $this->artisan('rovi:sync-steps')->assertExitCode(0);

    expect(TodaySteps::get())->toBe(2240);
});

it('leaves the previous count alone when rovi returns nothing for today', function () {
    TodaySteps::put(2240);
    fakeRoviSteps([]);

    $this->artisan('rovi:sync-steps')->assertExitCode(0);

    // A transient outage must not blank the status bar mid-day.
    expect(TodaySteps::get())->toBe(2240);
});

it('does not report a count cached for an earlier day', function () {
    TodaySteps::put(14332, Carbon::today()->subDay());

    expect(TodaySteps::get())->toBeNull();
});

it('shares todays steps with every page', function () {
    TodaySteps::put(2240);

    $this->get('/')->assertInertia(fn ($page) => $page->where('todaySteps', 2240));
});
