<?php

use App\Models\Calorie;
use App\Queries\LoggingStreak;
use Illuminate\Support\Carbon;

use function Pest\Laravel\get;

function logDay(string $date): void
{
    Calorie::factory()->create(['occurred_at' => $date.' 12:00:00']);
}

it('counts consecutive days back from today', function () {
    Carbon::setTestNow('2026-08-24 09:00:00');

    foreach (['2026-08-22', '2026-08-23', '2026-08-24'] as $date) {
        logDay($date);
    }

    expect(app(LoggingStreak::class)())->toBe(3);
});

it('holds the streak on a day not yet logged', function () {
    Carbon::setTestNow('2026-08-24 09:00:00');

    // Nothing eaten yet today: the run through yesterday still stands, rather
    // than resetting to zero every midnight.
    foreach (['2026-08-22', '2026-08-23'] as $date) {
        logDay($date);
    }

    expect(app(LoggingStreak::class)())->toBe(2);
});

it('stops at a missed day', function () {
    Carbon::setTestNow('2026-08-24 09:00:00');

    foreach (['2026-08-20', '2026-08-23', '2026-08-24'] as $date) {
        logDay($date);
    }

    expect(app(LoggingStreak::class)())->toBe(2);
});

it('is zero when nothing has been logged', function () {
    Carbon::setTestNow('2026-08-24 09:00:00');

    expect(app(LoggingStreak::class)())->toBe(0);
});

it('recounts as soon as a new day is logged', function () {
    Carbon::setTestNow('2026-08-24 09:00:00');

    logDay('2026-08-23');
    expect(app(LoggingStreak::class)())->toBe(1);

    // Without the observer dropping the cache this would stay at 1 until
    // midnight, which is the whole point of the badge being live.
    logDay('2026-08-24');
    expect(app(LoggingStreak::class)())->toBe(2);
});

it('shares the streak with every page', function () {
    Carbon::setTestNow('2026-08-24 09:00:00');

    logDay('2026-08-23');
    logDay('2026-08-24');

    get('/')->assertOk()->assertInertia(fn ($page) => $page->where('streakDays', 2));
});
