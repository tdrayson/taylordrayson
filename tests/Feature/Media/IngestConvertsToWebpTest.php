<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

/**
 * Raw bytes of an image in the given format, as a third party would send them.
 * Noise rather than flat colour: a solid PNG compresses smaller than any WebP of
 * it, so PrepareImage would rightly keep the PNG and the test would prove nothing.
 */
function bytesIn(string $format, int $width = 2400, int $height = 1600): string
{
    $image = new Imagick;
    $image->newPseudoImage($width, $height, 'plasma:fractal');
    $image->setImageFormat($format);

    return $image->getImageBlob();
}

it('stores a pulled jpeg as a webp original', function () {
    $media = Activity::factory()->create()
        ->addMediaFromString(bytesIn('jpeg'))
        ->usingFileName('strava-photo.jpg')
        ->toMediaCollection('photos');

    expect($media->mime_type)->toBe('image/webp')
        ->and($media->file_name)->toEndWith('.webp');

    $stored = new Imagick($media->getPath());

    expect($stored->getImageFormat())->toBe('WEBP')
        ->and(max($stored->getImageWidth(), $stored->getImageHeight()))->toBeLessThanOrEqual(1920);
});

it('stores a pulled png map as a webp original', function () {
    $media = Activity::factory()->create()
        ->addMediaFromString(bytesIn('png', 1600, 1000))
        ->usingFileName('map.png')
        ->toMediaCollection('map');

    expect($media->mime_type)->toBe('image/webp')
        ->and($media->file_name)->toEndWith('.webp')
        ->and((new Imagick($media->getPath()))->getImageFormat())->toBe('WEBP');
});

it('leaves an svg alone, since rasterising a vector is a downgrade', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';

    $media = Activity::factory()->create()
        ->addMediaFromString($svg)
        ->usingFileName('brand.svg')
        ->toMediaCollection('logo');

    expect($media->file_name)->toEndWith('.svg')
        ->and($media->mime_type)->toBe('image/svg+xml');
});
