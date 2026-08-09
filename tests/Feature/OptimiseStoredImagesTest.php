<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

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
 *
 * Only `full` is withheld. Withholding `card` too broke /photos outright:
 * getUrl() resolves against registered conversions, so an unregistered `card`
 * throws InvalidConversion even where the file already exists on disk. The
 * gallery read is exercised here because that is what actually fell over.
 */
it('leaves svg and gif out of full without breaking the gallery', function (string $bytes, string $name) {
    $activity = activityWithPhoto($bytes, $name);
    $media = $activity->getFirstMedia('photos');

    expect($media->hasGeneratedConversion('full'))->toBeFalse();

    $photos = $activity->galleryPhotos();

    expect($photos)->toHaveCount(1)
        ->and($photos[0]['src'])->toBeString()
        ->and($photos[0]['full'])->toBeString();
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
