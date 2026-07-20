<?php

use App\Data\PhotoData;

it('serialises a gallery photo with coordinate keys even when null', function () {
    expect(PhotoData::gallery('src.webp', 'srcset', 'full.webp', null, null)->toArray())->toBe([
        'src' => 'src.webp',
        'srcset' => 'srcset',
        'full' => 'full.webp',
        'latitude' => null,
        'longitude' => null,
    ]);
});

it('serialises a gallery photo with real coordinates', function () {
    expect(PhotoData::gallery('src.webp', null, 'full.webp', 51.1, -0.1)->toArray())->toBe([
        'src' => 'src.webp',
        'srcset' => null,
        'full' => 'full.webp',
        'latitude' => 51.1,
        'longitude' => -0.1,
    ]);
});

it('serialises a cover photo with no coordinate keys at all', function () {
    expect(PhotoData::cover('src.webp', 'srcset', 'full.webp')->toArray())->toBe([
        'src' => 'src.webp',
        'srcset' => 'srcset',
        'full' => 'full.webp',
    ]);
});
