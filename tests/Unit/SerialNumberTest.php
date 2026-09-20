<?php

use App\Support\SerialNumber;
use Carbon\CarbonImmutable;

it('prints the entry moment as a unix timestamp', function () {
    $when = CarbonImmutable::parse('2026-09-09 20:00:00Z');

    expect(SerialNumber::for($when))->toBe((string) $when->getTimestamp())
        ->and(SerialNumber::for($when))->toMatch('/^\d{10}$/');
});

it('gives a whole day one serial, since a day is one receipt', function () {
    // Every food row on a day carries the same occurred_at, so the receipt
    // number does not change with whichever row answers the URL.
    $end = CarbonImmutable::parse('2026-09-19 23:59:59');

    expect(SerialNumber::for($end))->toBe(SerialNumber::for($end->copy()));
});

it('falls back to zero for an entry with no moment', function () {
    expect(SerialNumber::for(null))->toBe('0');
});
