<?php

use App\Support\Downsample;

it('returns every index when under the cap', function () {
    expect(Downsample::indices(3, 240))->toBe([0, 1, 2]);
    expect(Downsample::indices(5, 0))->toBe([0, 1, 2, 3, 4]);
});

it('picks evenly spaced indices including both ends when over the cap', function () {
    $indices = Downsample::indices(100, 5);

    expect($indices)->toHaveCount(5);
    expect($indices[0])->toBe(0);
    expect($indices[2])->toBe(50);
    expect($indices[4])->toBe(99);
});

it('handles empty series and a cap of one without dividing by zero', function () {
    expect(Downsample::indices(0, 240))->toBe([]);
    expect(Downsample::indices(100, 1))->toBe([0]);
});
