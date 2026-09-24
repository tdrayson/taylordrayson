<?php

use App\Models\Film;
use App\Models\Place;
use App\Models\TvShow;
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
    $place = Place::factory()->create();
    $place->addMediaFromString(galleryJpegBytes())->usingFileName('me.jpg')->toMediaCollection('photos');

    // A film poster (cover) and a TvShow poster (non-timeline) — must not.
    Film::factory()->create()
        ->addMediaFromString(galleryJpegBytes())->usingFileName('poster.jpg')->toMediaCollection('cover');
    TvShow::factory()->create()
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
