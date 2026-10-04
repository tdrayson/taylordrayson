<?php

use App\Data\PhotoData;

it('serialises a gallery photo with coordinate keys even when null', function () {
    expect(PhotoData::gallery(1, 'src.webp', 'srcset', 'full.webp', null, null, null, null)->toArray())->toBe([
        'id' => 1,
        'src' => 'src.webp',
        'srcset' => 'srcset',
        'full' => 'full.webp',
        'alt' => null,
        'caption' => null,
        'latitude' => null,
        'longitude' => null,
    ]);
});

it('serialises a gallery photo with real coordinates', function () {
    expect(PhotoData::gallery(2, 'src.webp', null, 'full.webp', 'A described photo', 'Whyteleafe', 51.1, -0.1)->toArray())->toBe([
        'id' => 2,
        'src' => 'src.webp',
        'srcset' => null,
        'full' => 'full.webp',
        'alt' => 'A described photo',
        'caption' => 'Whyteleafe',
        'latitude' => 51.1,
        'longitude' => -0.1,
    ]);
});

it('serialises a cover photo with no coordinate keys at all', function () {
    expect(PhotoData::cover(3, 'src.webp', 'srcset', 'full.webp', null, null)->toArray())->toBe([
        'id' => 3,
        'src' => 'src.webp',
        'srcset' => 'srcset',
        'full' => 'full.webp',
        'alt' => null,
        'caption' => null,
    ]);
});
