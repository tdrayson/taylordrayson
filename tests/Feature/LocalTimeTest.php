<?php

use App\Support\LocalTime;
use Carbon\CarbonImmutable;

it('formats local time, label, offset and iso from a wall-clock and timezone', function () {
    $result = LocalTime::for(CarbonImmutable::parse('2026-07-01 09:30:00'), 'Europe/London');

    expect($result['time'])->toBe('9:30am');
    expect($result['label'])->toBe('Wed 1 Jul 2026, 9:30am');
    expect($result['offset'])->toBe('+01:00');
    expect($result['iso'])->toBe('2026-07-01T09:30:00+01:00');
});

it('is DST aware (London in January is +00:00)', function () {
    expect(LocalTime::for(CarbonImmutable::parse('2026-01-15 09:30:00'), 'Europe/London')['offset'])->toBe('+00:00');
});

it('uses a non-home zone correctly', function () {
    $result = LocalTime::for(CarbonImmutable::parse('2026-07-01 09:30:00'), 'America/New_York');

    expect($result['time'])->toBe('9:30am');
    expect($result['offset'])->toBe('-04:00');
});

it('falls back to the configured home timezone when null', function () {
    config(['app.home_timezone' => 'Europe/London']);

    expect(LocalTime::for(CarbonImmutable::parse('2026-07-01 09:30:00'), null)['offset'])->toBe('+01:00');
});
