<?php

use App\Models\Appearance;
use App\Presenters\CardPresenter;
use App\Support\YouTube;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

use function Pest\Laravel\get;

/**
 * Build a real JPEG of the given dimensions so getimagesizefromstring() can
 * decode its size the way the command does for live YouTube responses.
 */
function jpegBytes(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * Decode the width of the stored cover original on the faked public disk.
 */
function storedCoverWidth(Appearance $appearance): int
{
    $media = $appearance->refresh()->getFirstMedia('cover');
    $bytes = Storage::disk('public')->get($media->getPathRelativeToRoot());

    return getimagesizefromstring($bytes)[0];
}

it('extracts a youtube id and builds a thumbnail url from any url shape', function () {
    expect(YouTube::id('https://www.youtube.com/watch?v=W7rO_mZTuWM'))->toBe('W7rO_mZTuWM');
    expect(YouTube::id('https://youtu.be/W7rO_mZTuWM'))->toBe('W7rO_mZTuWM');
    expect(YouTube::id('https://example.com/not-youtube'))->toBeNull();
    expect(YouTube::id(null))->toBeNull();

    expect(YouTube::thumbnail('https://youtu.be/W7rO_mZTuWM'))
        ->toBe('https://i.ytimg.com/vi/W7rO_mZTuWM/maxresdefault.jpg');
    expect(YouTube::thumbnail('https://example.com/x'))->toBeNull();
});

it('exposes a media block with a derived youtube thumbnail when there is no cover', function () {
    $appearance = Appearance::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
        'audio_url' => null,
    ]);

    $media = CardPresenter::for($appearance)->meta->media;

    expect($media->id)->toBe("appearance-{$appearance->id}");
    expect($media->videoUrl)->toBe('https://www.youtube.com/watch?v=W7rO_mZTuWM');
    expect($media->thumbnail)->toBe('https://i.ytimg.com/vi/W7rO_mZTuWM/maxresdefault.jpg');
    expect($media->srcset)->toBeNull();
    expect($media->audioUrl)->toBeNull();
});

it('prefers a stored cover over the derived thumbnail', function () {
    Storage::fake('public');

    $appearance = Appearance::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
    ]);
    $appearance->addMediaFromString(jpegBytes(1280, 720))->usingFileName('cover.jpg')->toMediaCollection('cover');

    $thumbnail = $appearance->refresh()->thumbnailUrl();

    expect($thumbnail)->not->toBeNull();
    expect($thumbnail)->not->toContain('ytimg');
});

it('renders the appearance detail page with a thumbnail and video url', function () {
    $appearance = Appearance::factory()->create([
        'title' => 'WP Wireframe Explained',
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
    ]);

    $url = $appearance->occurred_at->format('Y/m/d').'/'.$appearance->slug();

    get("/{$url}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Entry')
        ->where('entry.video_url', 'https://www.youtube.com/watch?v=W7rO_mZTuWM')
        ->where('entry.thumbnail', 'https://i.ytimg.com/vi/W7rO_mZTuWM/maxresdefault.jpg')
    );
});

it('downloads and stores a youtube thumbnail as a cover media', function () {
    Storage::fake('public');
    Saloon::fake(['' => MockResponse::make(jpegBytes(1280, 720), 200)]);

    $appearance = Appearance::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
    ]);

    $this->artisan('appearances:thumbnails')->assertSuccessful();

    $cover = $appearance->refresh()->getFirstMedia('cover');

    expect($cover)->not->toBeNull();
    expect($cover->collection_name)->toBe('cover');
    expect(storedCoverWidth($appearance))->toBe(1280);
});

it('skips appearances that already have a cover unless forced', function () {
    Storage::fake('public');
    Saloon::fake(['' => MockResponse::make(jpegBytes(1280, 720), 200)]);

    $appearance = Appearance::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
    ]);
    $appearance->addMediaFromString(jpegBytes(640, 480))->usingFileName('existing.jpg')->toMediaCollection('cover');
    $originalId = $appearance->refresh()->getFirstMedia('cover')->id;

    $this->artisan('appearances:thumbnails')->assertSuccessful();

    expect($appearance->refresh()->getFirstMedia('cover')->id)->toBe($originalId);
    Saloon::assertNothingSent();
});

it('skips the grey placeholder and keeps the largest real image', function () {
    Storage::fake('public');
    Saloon::fake([
        'i.ytimg.com/vi/*/maxresdefault.jpg' => MockResponse::make(jpegBytes(1280, 720), 200),
    ]);

    $appearance = Appearance::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
    ]);

    $this->artisan('appearances:thumbnails')->assertSuccessful();

    expect(storedCoverWidth($appearance))->toBe(1280);
});

it('rejects a placeholder-sized response and drops to the next tier', function () {
    Storage::fake('public');
    Saloon::fake([
        // YouTube serves a 120x90 grey placeholder at 200 for a missing max size.
        'i.ytimg.com/vi/*/maxresdefault.jpg' => MockResponse::make(jpegBytes(120, 90), 200),
        'i.ytimg.com/vi/*/sddefault.jpg' => MockResponse::make(jpegBytes(640, 480), 200),
    ]);

    $appearance = Appearance::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
    ]);

    $this->artisan('appearances:thumbnails')->assertSuccessful();

    expect(storedCoverWidth($appearance))->toBe(640);
});

it('falls back to the high-quality thumbnail when larger sizes are missing', function () {
    Storage::fake('public');
    Saloon::fake([
        'i.ytimg.com/vi/*/maxresdefault.jpg' => MockResponse::make('', 404),
        'i.ytimg.com/vi/*/sddefault.jpg' => MockResponse::make('', 404),
        'i.ytimg.com/vi/*/hqdefault.jpg' => MockResponse::make(jpegBytes(480, 360), 200),
    ]);

    $appearance = Appearance::factory()->create([
        'video_url' => 'https://www.youtube.com/watch?v=W7rO_mZTuWM',
    ]);

    $this->artisan('appearances:thumbnails')->assertSuccessful();

    expect(storedCoverWidth($appearance))->toBe(480);
});
