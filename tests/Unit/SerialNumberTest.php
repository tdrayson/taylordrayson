<?php

use App\Support\SerialNumber;
use Carbon\CarbonImmutable;

it('dates a serial and keeps the sequence unique within that date', function () {
    $when = CarbonImmutable::parse('2026-09-09 20:00:00');

    expect(SerialNumber::for($when, 1624))->toBe('20260909-1624')
        ->and(SerialNumber::for($when, 7))->toBe('20260909-0007')
        // Two entries logged in the same second still differ, which a bare
        // epoch could not manage: 23,363 food rows share an occurred_at.
        ->and(SerialNumber::for($when, 1))->not->toBe(SerialNumber::for($when, 2));
});

it('falls back to the bare sequence when an entry has no date', function () {
    expect(SerialNumber::for(null, 42))->toBe('0042');
});

it('gives a day the same serial whichever of its rows is asked', function () {
    $when = CarbonImmutable::parse('2026-09-19 23:59:59');

    // Five food rows share a day and a URL, so they are one receipt.
    expect(SerialNumber::forDay($when))->toBe('20260919')
        ->and(SerialNumber::forDay($when))->toBe(SerialNumber::forDay($when->addSeconds(0)));
});
