<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * A photographic-looking JPEG rather than a flat colour: a solid image
 * compresses to almost nothing in any format, which would make a size
 * comparison prove nothing.
 */
function noisyJpeg(int $width = 2400, int $height = 1600): string
{
    $image = imagecreatetruecolor($width, $height);

    for ($x = 0; $x < $width; $x += 4) {
        for ($y = 0; $y < $height; $y += 4) {
            imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, imagecolorallocate($image, ($x * 7) % 255, ($y * 13) % 255, ($x + $y) % 255));
        }
    }

    ob_start();
    imagejpeg($image, null, 92);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function activityWithPhoto(string $bytes, string $name = 'photo.jpg'): Activity
{
    $activity = Activity::factory()->create();
    $activity->addMediaFromString($bytes)->usingFileName($name)->toMediaCollection('photos');

    return $activity->fresh();
}

it('renders a full copy no larger than 1920 on its longest side', function () {
    $activity = activityWithPhoto(noisyJpeg());

    $media = $activity->getFirstMedia('photos');
    expect($media->hasGeneratedConversion('full'))->toBeTrue();

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get(
        str_replace(Storage::disk('public')->path(''), '', $media->getPath('full'))
    ));

    expect(max($width, $height))->toBe(1920)
        // 2400x1600 keeps its 3:2 shape rather than being squared off.
        ->and($height)->toBe(1280);
});

/**
 * The saving is the entire point of the change, so it is worth asserting
 * rather than assuming: a conversion that quietly came out larger would
 * otherwise look like a success.
 */
it('produces a smaller file than the original', function () {
    $activity = activityWithPhoto(noisyJpeg());
    $media = $activity->getFirstMedia('photos');

    expect(filesize($media->getPath('full')))->toBeLessThan($media->size);
});

it('leaves the original in place', function () {
    $activity = activityWithPhoto(noisyJpeg());
    $media = $activity->getFirstMedia('photos');

    // The optimised copy sits alongside the import; nothing about rendering it
    // removes what it was made from.
    expect(is_file($media->getPath()))->toBeTrue()
        ->and(is_file($media->getPath('full')))->toBeTrue();
});

/**
 * Vector art rasterised to 1920 would be a downgrade, and an animated GIF
 * flattened by Imagick loses the animation silently.
 */
it('leaves svg and gif exactly as they were', function (string $bytes, string $name) {
    $activity = activityWithPhoto($bytes, $name);
    $media = $activity->getFirstMedia('photos');

    expect($media->hasGeneratedConversion('full'))->toBeFalse()
        ->and($media->hasGeneratedConversion('card'))->toBeFalse();
})->with([
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>', 'logo.svg'],
    'gif' => [base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'loop.gif'],
]);

it('falls back to the original until a copy exists', function () {
    $activity = Activity::factory()->create();
    expect($activity->optimisedUrl('map'))->toBeNull();

    $activity->addMediaFromString(noisyJpeg(200, 200))->usingFileName('m.jpg')->toMediaCollection('map');
    $activity = $activity->fresh();

    expect($activity->optimisedUrl('map'))->toContain('-full.webp');
});

it('reports the saving without deleting anything', function () {
    $activity = activityWithPhoto(noisyJpeg());
    $original = $activity->getFirstMedia('photos')->getPath();

    $this->artisan('media:optimise --report')
        ->expectsOutputToContain('Nothing has been deleted')
        ->assertSuccessful();

    expect(is_file($original))->toBeTrue();
});
