<?php

use App\Models\Activity;
use App\Models\Appearance;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

/** Attach a real JPEG to a model's media collection. */
function galleryPhoto(object $model, string $collection, string $name): void
{
    $image = imagecreatetruecolor(20, 20);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    $model->addMediaFromString($bytes)->usingFileName($name)->toMediaCollection($collection);
}

it('renders the photos page', function () {
    get('/photos')->assertOk()->assertInertia(fn ($page) => $page->component('Photos')->has('photos', 0));
});

it('shows only real photos, newest first, excluding maps and appearance thumbnails', function () {
    Storage::fake('public');

    $older = Activity::factory()->create(['type' => 'run', 'name' => 'Older run', 'occurred_at' => '2026-01-01 09:00:00']);
    galleryPhoto($older, 'cover', 'older.jpg');

    $newer = Activity::factory()->create(['type' => 'run', 'name' => 'Newer run', 'occurred_at' => '2026-06-01 09:00:00']);
    galleryPhoto($newer, 'cover', 'newer-cover.jpg');
    galleryPhoto($newer, 'photos', 'newer-extra.jpg');
    galleryPhoto($newer, 'map', 'newer-map.jpg');

    $appearance = Appearance::factory()->create(['occurred_at' => '2026-07-01 09:00:00']);
    galleryPhoto($appearance, 'cover', 'youtube-thumb.jpg');

    get('/photos')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Photos')
        ->has('photos', 3) // 2 from the newer activity + 1 from the older; map + appearance excluded
        ->where('photos.0.caption', 'Newer run') // newest first
        ->has('photos.0.full')
        ->has('photos.0.url')
        ->has('photos.0.date')
    );
});
