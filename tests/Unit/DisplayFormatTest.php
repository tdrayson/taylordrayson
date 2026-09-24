<?php

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Support\DisplayFormat;
use App\Support\Preferences;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

$fixtures = json_decode(file_get_contents(__DIR__.'/../Fixtures/display-formats.json'), true);

it('writes a clock reading in each time format', function (array $case) {
    foreach (TimeFormat::cases() as $format) {
        expect((new DisplayFormat(time: $format))->time(CarbonImmutable::parse($case['at'])))->toBe($case[$format->value]);
    }
})->with(array_map(fn (array $case): array => [$case], $fixtures['time']));

it('writes a date in each date format', function (array $case) {
    foreach (DateFormat::cases() as $format) {
        expect((new DisplayFormat(date: $format))->date(CarbonImmutable::parse($case['at'])))->toBe($case[$format->value]);
    }
})->with(array_map(fn (array $case): array => [$case], $fixtures['date']));

it('writes a range in each date format', function (array $case) {
    foreach (DateFormat::cases() as $format) {
        $range = (new DisplayFormat(date: $format))->range(CarbonImmutable::parse($case['start']), CarbonImmutable::parse($case['end']));

        expect($range)->toBe($case[$format->value]);
    }
})->with(array_map(fn (array $case): array => [$case], $fixtures['range']));

it('reads the formats from the visitor cookies and ignores unknown values', function () {
    $chosen = DisplayFormat::for(Request::create('/', cookies: [
        Preferences::SETTING_PREFIX.'timeFormat' => '24h',
        Preferences::SETTING_PREFIX.'dateFormat' => 'iso',
    ]));
    $junk = DisplayFormat::for(Request::create('/', cookies: [
        Preferences::SETTING_PREFIX.'timeFormat' => '"><script>',
        Preferences::SETTING_PREFIX.'dateFormat' => 'nope',
    ]));

    expect($chosen->time)->toBe(TimeFormat::TwentyFourHour)
        ->and($chosen->date)->toBe(DateFormat::Iso)
        ->and($junk->time)->toBe(TimeFormat::TwelveHour)
        ->and($junk->date)->toBe(DateFormat::Short);
});
