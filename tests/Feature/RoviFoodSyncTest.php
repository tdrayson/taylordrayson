<?php

use App\Models\Calorie;
use App\Models\TimelineEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.rovi.key' => 'test-key', 'services.rovi.base_url' => 'https://rovi.test/personalApi']);
    Carbon::setTestNow('2026-06-30 10:00:00');

    // Register the Rovi food stub once; each test drives the response through
    // these globals. Re-calling Http::fake() would merge stubs (first match
    // wins), so a single closure that reads mutable state is the reliable way
    // to return different responses across successive command runs.
    $GLOBALS['__rovi_food'] = [];
    $GLOBALS['__rovi_status'] = 200;

    Http::fake(['*/v1/me/food*' => function () {
        if (($GLOBALS['__rovi_status'] ?? 200) !== 200) {
            return Http::response('upstream error', $GLOBALS['__rovi_status']);
        }

        return Http::response([
            'data' => $GLOBALS['__rovi_food'] ?? [],
            'paging' => ['nextCursor' => null, 'hasMore' => false],
            'meta' => ['uid' => 'u1', 'endpoint' => '/v1/me/food', 'generatedAt' => '2026-06-30T10:00:00Z'],
        ]);
    }]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A Rovi diary item with sensible defaults. */
function roviFood(string $id, string $dateKey, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'dateKey' => $dateKey,
        'name' => "Food {$id}",
        'emoji' => '🍽️',
        'mealType' => 'Lunch',
        'quantity' => 1,
        'servingUnit' => 'serving',
        'servingSize' => 1,
        'baseQuantity' => 1,
        'baseUnit' => 'serving',
        'calories' => 100,
        'fats' => 5,
        'protein' => 3,
        'carbs' => 10,
        'fibre' => 1,
    ], $overrides);
}

/** Set what the next Rovi food fetch will return. */
function setRoviFood(array $items): void
{
    $GLOBALS['__rovi_food'] = $items;
    $GLOBALS['__rovi_status'] = 200;
}

/** Make the next Rovi food fetch fail. */
function setRoviFailure(int $status = 500): void
{
    $GLOBALS['__rovi_status'] = $status;
}

function calorieEntryCount(): int
{
    return TimelineEntry::where('timelineable_type', Calorie::class)->count();
}

it('maps diary items to calories and creates one timeline entry per day', function () {
    setRoviFood([
        roviFood('a', '2026-06-30', ['name' => 'Pain Au Chocolate', 'mealType' => 'Breakfast', 'calories' => 269, 'protein' => 4]),
        roviFood('b', '2026-06-30', ['mealType' => 'Snack']),
    ]);

    $this->artisan('rovi:sync-food')->assertSuccessful();

    expect(Calorie::where('source', 'rovi')->count())->toBe(2)
        ->and(calorieEntryCount())->toBe(1); // one daily entry, not one per item

    $a = Calorie::where('source_id', 'a')->first();
    expect($a->name)->toBe('Pain Au Chocolate')
        ->and($a->meal)->toBe('breakfast')
        ->and($a->calories)->toBe(269)
        ->and((int) $a->protein)->toBe(4)
        ->and($a->occurred_at->toDateString())->toBe('2026-06-30');

    // Snack normalises to the plural the app stores.
    expect(Calorie::where('source_id', 'b')->first()->meal)->toBe('snacks');
});

it('is idempotent and updates changed items in place', function () {
    setRoviFood([roviFood('a', '2026-06-30', ['calories' => 100])]);
    $this->artisan('rovi:sync-food')->assertSuccessful();

    setRoviFood([roviFood('a', '2026-06-30', ['calories' => 150])]);
    $this->artisan('rovi:sync-food')->assertSuccessful();

    expect(Calorie::where('source', 'rovi')->count())->toBe(1)
        ->and(Calorie::where('source_id', 'a')->first()->calories)->toBe(150);
});

it('picks up food logged late for an earlier day in the window', function () {
    setRoviFood([roviFood('today', '2026-06-30')]);
    $this->artisan('rovi:sync-food')->assertSuccessful();
    expect(Calorie::where('source', 'rovi')->count())->toBe(1);

    // Next run: yesterday gains an entry that was logged after the fact.
    setRoviFood([roviFood('today', '2026-06-30'), roviFood('late', '2026-06-29')]);
    $this->artisan('rovi:sync-food')->assertSuccessful();

    expect(Calorie::where('source', 'rovi')->count())->toBe(2)
        ->and(Calorie::where('source_id', 'late')->first()->occurred_at->toDateString())->toBe('2026-06-29')
        ->and(calorieEntryCount())->toBe(2); // a daily entry for each of the two days
});

it('removes rows deleted in Rovi and drops the day entry when emptied', function () {
    setRoviFood([roviFood('a', '2026-06-30'), roviFood('b', '2026-06-30')]);
    $this->artisan('rovi:sync-food')->assertSuccessful();
    expect(Calorie::where('source', 'rovi')->count())->toBe(2);

    // b is deleted in Rovi; a remains, so the day's entry stays.
    setRoviFood([roviFood('a', '2026-06-30')]);
    $this->artisan('rovi:sync-food')->assertSuccessful();
    expect(Calorie::where('source', 'rovi')->count())->toBe(1)
        ->and(Calorie::where('source_id', 'b')->exists())->toBeFalse()
        ->and(calorieEntryCount())->toBe(1);

    // Everything for the window is gone; the day's entry is removed too.
    setRoviFood([]);
    $this->artisan('rovi:sync-food')->assertSuccessful();
    expect(Calorie::where('source', 'rovi')->count())->toBe(0)
        ->and(calorieEntryCount())->toBe(0);
});

it('skips the run without deleting when the API fails', function () {
    setRoviFood([roviFood('a', '2026-06-30')]);
    $this->artisan('rovi:sync-food')->assertSuccessful();
    expect(Calorie::where('source', 'rovi')->count())->toBe(1);

    setRoviFailure();
    $this->artisan('rovi:sync-food')->assertSuccessful();

    expect(Calorie::where('source', 'rovi')->count())->toBe(1); // untouched, not wiped
});

it('supersedes pre-existing CSV rows on a date Rovi logs', function () {
    $csvRow = Calorie::create([
        'occurred_at' => '2026-06-30 00:00:00', // same day Rovi will log
        'source' => null,
        'name' => 'Stale import',
        'meal' => 'lunch',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 999,
    ]);

    setRoviFood([roviFood('a', '2026-06-30')]);
    $this->artisan('rovi:sync-food')->assertSuccessful();

    expect(Calorie::find($csvRow->id))->toBeNull() // superseded by Rovi for that day
        ->and(Calorie::whereDate('occurred_at', '2026-06-30')->count())->toBe(1)
        ->and(Calorie::whereDate('occurred_at', '2026-06-30')->first()->source)->toBe('rovi');
});

it('never touches non-Rovi (historical CSV) rows on dates Rovi did not log', function () {
    $csvRow = Calorie::create([
        'occurred_at' => '2026-06-29 00:00:00',
        'source' => null,
        'name' => 'Imported lunch',
        'meal' => 'lunch',
        'quantity' => 1,
        'units' => 'serving',
        'calories' => 500,
    ]);

    setRoviFood([roviFood('a', '2026-06-30')]);
    $this->artisan('rovi:sync-food')->assertSuccessful();

    expect(Calorie::find($csvRow->id))->not->toBeNull()
        ->and(Calorie::whereNull('source')->count())->toBe(1);
});
