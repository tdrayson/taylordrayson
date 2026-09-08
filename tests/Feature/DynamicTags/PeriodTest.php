<?php

use App\DynamicTags\DynamicTagRegistry;
use App\Models\Note;
use App\Support\Period;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-09-08 12:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('resolves a bare year to that calendar year', function () {
    $period = Period::from(['period' => '2025']);

    expect($period->start->toDateString())->toBe('2025-01-01')
        ->and($period->end->toDateString())->toBe('2025-12-31');
});

it('resolves a preset relative to now', function () {
    $period = Period::from(['period' => 'last-30-days']);

    expect($period->start->toDateString())->toBe('2026-08-09')
        ->and($period->end->toDateString())->toBe('2026-09-08');
});

it('resolves an explicit range', function () {
    $period = Period::from(['from' => '2025-01-01', 'to' => '2025-06-30']);

    expect($period->end->toDateString())->toBe('2025-06-30');
});

it('is unbounded by default', function () {
    expect(Period::from([])->start)->toBeNull();
});

it('counts entries within a period', function () {
    Note::factory()->create(['occurred_at' => '2025-06-01 10:00:00']);
    Note::factory()->create(['occurred_at' => '2026-06-01 10:00:00']);

    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('entries.count', ['period' => '2025'])['value'])->toBe(1)
        ->and($registry->value('entries.count', [])['value'])->toBe(2);
});
