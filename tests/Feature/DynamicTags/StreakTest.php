<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Models\Flight;
use App\Models\Food;
use App\Queries\LoggingStreak;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-08 12:00:00');
    Cache::flush();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('counts a current daily streak, ignoring an empty today', function () {
    foreach (['2026-09-05', '2026-09-06', '2026-09-07'] as $day) {
        Food::factory()->create(['occurred_at' => "{$day} 12:00:00"]);
    }

    expect(app(DynamicTagRegistry::class)->value('streak.current', ['type' => 'food'])['value'])
        ->toBe(3);
});

it('counts the longest daily streak even once broken', function () {
    foreach (['2026-01-01', '2026-01-02', '2026-01-03', '2026-06-01'] as $day) {
        Food::factory()->create(['occurred_at' => "{$day} 12:00:00"]);
    }

    expect(app(DynamicTagRegistry::class)->value('streak.longest', ['type' => 'food'])['value'])
        ->toBe(3);
});

it('counts a yearly streak, which is what makes episodic types meaningful', function () {
    foreach (['2022', '2023', '2024', '2025', '2026'] as $year) {
        Flight::factory()->create(['occurred_at' => "{$year}-06-01 12:00:00"]);
    }

    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('streak.longest', ['type' => 'flight', 'every' => 'year'])['value'])->toBe(5)
        ->and($registry->value('streak.longest', ['type' => 'flight'])['value'])->toBe(1);
});

it('defaults to the food streak', function () {
    Food::factory()->create(['occurred_at' => '2026-09-07 12:00:00']);

    expect(app(DynamicTagRegistry::class)->value('streak.current', [])['value'])->toBe(1);
});

it('holds the current streak just after local midnight during BST, when app.timezone is UTC', function () {
    // 00:30 in Europe/London during BST is still 23:30 the previous day in
    // UTC, so a "now" resolved via app.timezone lands a whole day early.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-01 00:30:00', 'Europe/London'));

    Food::factory()->create(['occurred_at' => '2026-06-30 20:00:00']);
    Food::factory()->create(['occurred_at' => '2026-07-01 00:15:00']);

    expect(app(DynamicTagRegistry::class)->value('streak.current', ['type' => 'food'])['value'])
        ->toBe(2);
});

it('holds the current streak when the sync is several days behind', function () {
    foreach (['2026-09-03', '2026-09-04', '2026-09-05'] as $day) {
        Food::factory()->create(['occurred_at' => "{$day} 12:00:00"]);
    }

    expect(app(DynamicTagRegistry::class)->value('streak.current', ['type' => 'food'])['value'])
        ->toBe(3);
});

it('always agrees with the sidebar streak', function (array $days) {
    foreach ($days as $day) {
        Food::factory()->create(['occurred_at' => "{$day} 12:00:00"]);
    }

    expect(app(DynamicTagRegistry::class)->value('streak.current', ['type' => 'food'])['value'])
        ->toBe(app(LoggingStreak::class)());
})->with([
    'unbroken to today' => [['2026-09-06', '2026-09-07', '2026-09-08']],
    'sync days behind' => [['2026-09-01', '2026-09-02']],
    'a missed day' => [['2026-09-04', '2026-09-06', '2026-09-07']],
    'a future-dated row' => [['2026-09-07', '2026-09-08', '2026-09-20']],
    'nothing logged' => [[]],
]);
