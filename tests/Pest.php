<?php

use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A JS expression evaluating to one cookie's value, or null when it is unset.
 *
 * Display preferences are cookies rather than local storage, so the server can
 * render the right scheme and units on the first paint.
 */
function cookieValue(string $name): string
{
    return "document.cookie.match(/(?:^|;\\s*){$name}=([^;]*)/)?.[1] ?? null";
}

/** A JS statement clearing every cookie on the current path. */
function clearCookies(): string
{
    return "document.cookie.split(';').forEach((c) => { document.cookie = c.replace(/^ +/, '').replace(/=.*/, '=;path=/;max-age=0'); })";
}

/** A real JPEG of the given size, so the media library can process it. */
function fakeJpeg(int $width = 800, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * A Strava photo payload, shaped like the real API response.
 *
 * @return array<string, mixed>
 */
/**
 * @param  array{0: float, 1: float}|null  $location  Strava's own per-photo GPS fix, when set.
 */
function stravaPhotoPayload(string $uniqueId, string $createdAt, ?array $location = null): array
{
    return array_filter([
        'unique_id' => $uniqueId,
        'created_at' => $createdAt,
        'source' => 1,
        'urls' => ['2048' => 'https://dgtzuqphqg23d.cloudfront.net/'.$uniqueId.'-1152x2048.jpg'],
        'sizes' => ['2048' => [1152, 2048]],
        'location' => $location,
    ], fn ($value): bool => $value !== null);
}

/**
 * A photographic-looking JPEG rather than a flat colour: a solid image
 * compresses to almost nothing in any format, which would make a size
 * comparison between the original and its optimised copy prove nothing.
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

/** An activity carrying one photo, for the conversion and prune tests. */
function activityWithPhoto(string $bytes, string $name = 'photo.jpg'): Activity
{
    $activity = Activity::factory()->create();
    $activity->addMediaFromString($bytes)->usingFileName($name)->toMediaCollection('photos');

    return $activity->fresh();
}

/**
 * Real PNG bytes for a faked Mapbox response.
 *
 * Placeholder strings ("PNGDATA") were fine while maps had no conversions, but
 * they now go through Imagick to render the optimised copy, and it throws on
 * anything it cannot decode. The tint keeps a light and a dark fake
 * distinguishable.
 */
function mapPng(int $tint = 200): string
{
    $image = imagecreatetruecolor(40, 21);
    imagefill($image, 0, 0, imagecolorallocate($image, $tint, $tint, $tint));

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}
