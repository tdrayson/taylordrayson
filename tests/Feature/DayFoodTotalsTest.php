<?php

use App\Models\Calorie;
use App\Queries\DayFoodTotals;
use Illuminate\Support\Facades\DB;

/*
 * Every food card shows the whole day rather than the item behind it, so a feed
 * asked for the same totals once per card, and `whereDate()` compiles to
 * strftime() over the column, which no index can serve.
 */

it('reads every day on the page in one query', function () {
    foreach (['2024-03-01', '2024-03-02', '2024-03-03'] as $day) {
        Calorie::factory()->count(2)->create(['occurred_at' => $day.' 09:00:00', 'calories' => 300, 'protein' => 10]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $totals = app(DayFoodTotals::class);
    $totals->warm(['2024-03-01', '2024-03-02', '2024-03-03']);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($totals->for('2024-03-02')['calories'])->toBe(600);

    // Warmed days are already known, so reading them costs nothing more.
    $totals->for('2024-03-01');
    $totals->for('2024-03-03');

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('compares occurred_at as a range so the index applies', function () {
    Calorie::factory()->create(['occurred_at' => '2024-03-01 09:00:00', 'calories' => 250]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(DayFoodTotals::class)->warm(['2024-03-01']);

    $sql = DB::getQueryLog()[0]['query'];

    // strftime is fine in the projection; in the filter it would scan the table.
    expect($sql)->toContain('"occurred_at" >=')
        ->and($sql)->toContain('"occurred_at" <')
        ->and($sql)->not->toContain("strftime('%Y-%m-%d', occurred_at) =");
});

it('reports a day with nothing logged as zero without asking twice', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    $totals = app(DayFoodTotals::class);

    expect($totals->for('2024-03-01')['calories'])->toBe(0);

    $totals->for('2024-03-01');

    expect(DB::getQueryLog())->toHaveCount(1);
});
