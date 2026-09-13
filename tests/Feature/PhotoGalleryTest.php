<?php

use App\Enums\MediaType;
use App\Models\Checkin;
use App\Models\Media;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function galleryJpegBytes(): string
{
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);
    imagedestroy($image);

    return ob_get_clean();
}

it('shows only photos taken, excluding posters and non-timeline art', function () {
    Storage::fake('public');

    // A real personal photo on a timeline entry — should appear.
    $checkin = Checkin::factory()->create();
    $checkin->addMediaFromString(galleryJpegBytes())->usingFileName('me.jpg')->toMediaCollection('photos');

    // A film poster (Media cover) and a Series poster (non-timeline) — must not.
    Media::factory()->create(['type' => MediaType::Film])
        ->addMediaFromString(galleryJpegBytes())->usingFileName('poster.jpg')->toMediaCollection('cover');
    Series::factory()->create()
        ->addMediaFromString(galleryJpegBytes())->usingFileName('series.jpg')->toMediaCollection('cover');

    // The gallery defers and paginates its photos, so the shell carries the
    // total and the deferred reload is what shapes the first page.
    $this->get('/photos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Photos')
            ->where('total', 1)
            ->missing('photos')
            ->loadDeferredProps(fn (Assert $page) => $page->has('photos.data', 1)));
});
