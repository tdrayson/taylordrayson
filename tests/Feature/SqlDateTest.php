<?php

use App\Models\Activity;
use App\Models\TimelineEntry;
use App\Queries\StatsForType;
use App\Support\SqlDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pins the date-part expressions to the same answers on every database.
 * strftime('%w') counts 0-6 from Sunday, MySQL's DAYOFWEEK() 1-7: getting that
 * wrong shifts the whole heatmap by a day with no error to show for it.
 */
it('numbers the days of the week the same way on any database', function () {
    // A known week: 2026-06-07 is a Sunday, so %w counts 0 through 6 from here.
    $expected = [
        '2026-06-07' => 0,
        '2026-06-08' => 1,
        '2026-06-09' => 2,
        '2026-06-10' => 3,
        '2026-06-11' => 4,
        '2026-06-12' => 5,
        '2026-06-13' => 6,
    ];

    foreach ($expected as $date => $dayNumber) {
        $sql = SqlDate::dayOfWeek("'{$date} 09:00:00'");

        expect((int) DB::selectOne("SELECT {$sql} AS v")->v)->toBe($dayNumber)
            // The same number PHP gives, which is what the grid is built around.
            ->and($dayNumber)->toBe((int) Carbon::parse($date)->format('w'));
    }
});

it('reads the hour and the month-day the same way on any database', function () {
    $hour = SqlDate::hour("'2026-06-07 17:42:00'");
    $monthDay = SqlDate::monthDay("'2026-06-07 17:42:00'");

    expect((int) DB::selectOne("SELECT {$hour} AS v")->v)->toBe(17)
        ->and(DB::selectOne("SELECT {$monthDay} AS v")->v)->toBe('06-07');
});

/**
 * The expressions above feed the stats heatmap, so this checks the whole path:
 * a session on a known day and hour has to land in the matching cell. The grid
 * is Monday-first, unlike the 0=Sunday the database reports.
 */
it('puts an activity in the right cell of the busiest-times grid', function () {
    // Sunday 09:00 and Wednesday 18:00.
    Activity::factory()->create(['occurred_at' => '2026-06-07 09:00:00', 'type' => 'run']);
    Activity::factory()->create(['occurred_at' => '2026-06-10 18:00:00', 'type' => 'run']);

    $stats = app(StatsForType::class)('activities', Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), 'none');
    $grid = $stats['busiest']['grid'];

    expect($grid[6][9])->toBe(1)  // Sunday is the last row
        ->and($grid[2][18])->toBe(1)  // Wednesday is the third
        ->and($stats['busiest']['max'])->toBe(1)
        ->and(array_sum(array_map('array_sum', $grid)))->toBe(2);
});

it('finds entries covering a month-day in any year', function () {
    $activity = Activity::factory()->create(['occurred_at' => '2019-06-03 10:00:00']);

    expect(TimelineEntry::query()->coveringAnniversary('06-03')->count())->toBe(1)
        ->and(TimelineEntry::query()->coveringAnniversary('06-04')->count())->toBe(0)
        ->and($activity->timelineEntry)->not->toBeNull();
});
