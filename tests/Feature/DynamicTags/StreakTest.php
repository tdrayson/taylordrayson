<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Models\Calorie;
use App\Models\Flight;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-08 12:00:00');
    Cache::flush();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('counts a current daily streak, ignoring an empty today', function () {
    foreach (['2026-09-05', '2026-09-06', '2026-09-07'] as $day) {
        Calorie::factory()->create(['occurred_at' => "{$day} 12:00:00"]);
    }

    expect(app(DynamicTagRegistry::class)->value('streak.current', ['type' => 'calorie'])['value'])
        ->toBe(3);
});

it('counts the longest daily streak even once broken', function () {
    foreach (['2026-01-01', '2026-01-02', '2026-01-03', '2026-06-01'] as $day) {
        Calorie::factory()->create(['occurred_at' => "{$day} 12:00:00"]);
    }

    expect(app(DynamicTagRegistry::class)->value('streak.longest', ['type' => 'calorie'])['value'])
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
    Calorie::factory()->create(['occurred_at' => '2026-09-07 12:00:00']);

    expect(app(DynamicTagRegistry::class)->value('streak.current', [])['value'])->toBe(1);
});
