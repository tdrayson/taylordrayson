<?php

use App\Support\SerialNumber;
use Carbon\CarbonImmutable;

it('encodes the entry moment, and decodes back to it', function () {
    $when = CarbonImmutable::parse('2026-09-09 20:00:00Z');
    $serial = SerialNumber::for($when);

    expect($serial)->toMatch('/^[A-HJ-NP-Z2-9]{8,}$/')
        ->and(SerialNumber::moment($serial)?->timestamp)->toBe($when->timestamp);
});

it('scatters adjacent moments, which is the whole reason for encoding them', function () {
    $when = CarbonImmutable::parse('2026-09-09 20:00:00Z');

    $serials = array_map(
        fn (int $offset): string => SerialNumber::for($when->addSeconds($offset)),
        range(0, 4),
    );

    // A raw epoch would differ in one digit and read as the same number.
    expect(array_unique($serials))->toHaveCount(5);

    foreach ($serials as $index => $serial) {
        foreach (array_slice($serials, $index + 1) as $other) {
            expect(similar_text($serial, $other))->toBeLessThan(6);
        }
    }
});

it('gives a whole day one serial, since a day is one receipt', function () {
    // Every food row on a day shares an occurred_at, so the receipt number
    // does not change with whichever row answers the URL.
    $end = CarbonImmutable::parse('2026-09-19 23:59:59');

    expect(SerialNumber::for($end))->toBe(SerialNumber::for($end->copy()));
});
