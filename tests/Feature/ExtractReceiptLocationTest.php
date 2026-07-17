<?php

use App\Actions\Fuel\ExtractReceiptLocation;

it('converts EXIF GPS rationals to a decimal coordinate', function () {
    $latitude = ExtractReceiptLocation::coordinate(['51/1', '22/1', '2322/100'], 'N');

    expect(round($latitude, 5))->toBe(51.37312);
});

it('negates southern and western coordinates', function () {
    $longitude = ExtractReceiptLocation::coordinate(['0/1', '7/1', '546/100'], 'W');

    expect($longitude)->toBeLessThan(0.0);
});

it('returns null for malformed coordinate parts', function () {
    expect(ExtractReceiptLocation::coordinate(null, 'N'))->toBeNull();
    expect(ExtractReceiptLocation::coordinate(['51/1'], 'N'))->toBeNull();
});
