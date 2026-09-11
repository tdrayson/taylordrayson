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

it('leaves a lone from bound open-ended', function () {
    $period = Period::from(['from' => '2025-06-01']);

    expect($period->start->toDateString())->toBe('2025-06-01')
        ->and($period->end)->toBeNull();
});

it('leaves a lone to bound open-ended', function () {
    $period = Period::from(['to' => '2025-06-30']);

    expect($period->start)->toBeNull()
        ->and($period->end->toDateString())->toBe('2025-06-30');
});

it('filters entries by a lone from bound', function () {
    Note::factory()->create(['occurred_at' => '2025-06-01 10:00:00']);
    Note::factory()->create(['occurred_at' => '2025-08-01 10:00:00']);

    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('entries.count', ['from' => '2025-07-01'])['value'])->toBe(1);
});

it('filters entries by a lone to bound', function () {
    Note::factory()->create(['occurred_at' => '2025-06-01 10:00:00']);
    Note::factory()->create(['occurred_at' => '2025-08-01 10:00:00']);

    $registry = app(DynamicTagRegistry::class);

    expect($registry->value('entries.count', ['to' => '2025-07-01'])['value'])->toBe(1);
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

it('degrades an unparseable from bound to an open-ended window instead of throwing', function () {
    // Reproduces {entries.count from:lastweek}, which used to 500 the entry
    // page and, via NoteCard, the timeline listing it too.
    expect(Period::from(['from' => 'lastweek'])->start)->toBeNull();

    $registry = app(DynamicTagRegistry::class);

    expect(fn () => $registry->value('entries.count', ['from' => 'lastweek']))->not->toThrow(Throwable::class);
});

it('resolves this-month against local time, not a UTC now still in the previous month', function () {
    // 00:30 in Europe/London during BST is 23:30 the previous day in UTC, so
    // a "now" resolved via app.timezone lands a whole month early on 1 July.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-01 00:30:00', 'Europe/London'));

    $period = Period::from(['period' => 'this-month']);

    expect($period->start->toDateString())->toBe('2026-07-01')
        ->and($period->end->toDateString())->toBe('2026-07-31');
});
