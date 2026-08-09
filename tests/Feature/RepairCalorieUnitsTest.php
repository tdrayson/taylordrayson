<?php

use App\Models\Calorie;
use Illuminate\Support\Facades\DB;

/**
 * Real numbers from the rows this was written for: 70g of onion rings imported
 * with its macros in milligrams, so 29g of carbs was stored as 28,990.
 */
function milligramOnionRings(array $overrides = []): Calorie
{
    return Calorie::factory()->create([...[
        'name' => 'Onion rings',
        'quantity' => 70,
        'units' => 'Grams',
        'calories' => 353,
        'fat' => 12079.4,
        'protein' => 3575.49,
        'carbs' => 28990.4,
        'saturated_fat' => 918.3,
        'sugars' => 3092.31,
        'fibre' => 1111.3,
        'sodium' => 918031,
    ], ...$overrides]);
}

it('brings milligram macros back to grams, rounded to the stored precision', function () {
    $row = milligramOnionRings();

    $this->artisan('calories:repair-units')->assertSuccessful();

    // Two places because the columns are decimal(8,2); SQLite would happily
    // keep more, and then the source and a copied MySQL database would differ.
    expect(DB::table('calories')->find($row->id))
        ->fat->toEqual(12.08)
        ->protein->toEqual(3.58)
        ->carbs->toEqual(28.99)
        ->saturated_fat->toEqual(0.92)
        ->sugars->toEqual(3.09)
        ->fibre->toEqual(1.11)
        ->sodium->toEqual(918.03)
        // Energy came from the same source and was never scaled.
        ->calories->toEqual(353);
});

/**
 * The safety property that matters most. Over-reaching here would replace
 * merely wrong numbers with far worse ones, and nothing would flag it.
 */
it('leaves rows that are wrong in some other way alone', function () {
    // 80g of baguette cannot hold 209g of carbs, so this is wrong too, but it
    // overshoots by a factor of three rather than a thousand: dividing it would
    // give 0.2g. Wrong in a different way is not this command's problem.
    $baguette = milligramOnionRings(['name' => 'White Baguette', 'quantity' => 80, 'carbs' => 209.4, 'fat' => 2.1, 'protein' => 8.3, 'sodium' => 490]);

    // No weight to judge against, so nothing can be concluded from the numbers.
    $serving = milligramOnionRings(['name' => 'Chicken Curry', 'quantity' => 1, 'units' => 'Serving', 'carbs' => 0, 'fat' => 595, 'protein' => 5168]);

    $this->artisan('calories:repair-units')->assertSuccessful();

    expect(DB::table('calories')->find($baguette->id))->carbs->toEqual(209.4)
        ->and(DB::table('calories')->find($serving->id))->protein->toEqual(5168);
});

it('can be run again without halving anything', function () {
    $row = milligramOnionRings();

    $this->artisan('calories:repair-units')->assertSuccessful();
    $this->artisan('calories:repair-units')->assertSuccessful();

    expect(DB::table('calories')->find($row->id))->carbs->toEqual(28.99);
});

it('writes nothing when only reporting', function () {
    $row = milligramOnionRings();

    $this->artisan('calories:repair-units', ['--pretend' => true])
        ->expectsOutputToContain('Onion rings')
        ->assertSuccessful();

    expect(DB::table('calories')->find($row->id))->carbs->toEqual(28990.4);
});
