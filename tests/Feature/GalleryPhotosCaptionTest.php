<?php

use App\Models\Activity;
use App\Support\GalleryPhotos;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');
});

it('gives an entry its own photo caption, and the /photos wall the entry title instead', function () {
    $activity = Activity::factory()->create(['type' => 'run', 'name' => 'Sunday long run']);
    $activity->addMediaFromString(fakeJpeg())
        ->usingFileName('p.jpg')
        ->withCustomProperties(['alt' => 'Clare mid-stride', 'caption' => 'Along the river'])
        ->toMediaCollection('cover');

    $entryPhoto = $activity->refresh()->galleryPhotos()[0];
    $galleryPhoto = GalleryPhotos::shape($activity, $activity->getMedia('cover'))[0];

    // On the entry's own page, the caption is the photo's own: you already
    // know which entry you're on, so the useful label is what it shows.
    expect($entryPhoto['alt'])->toBe('Clare mid-stride')
        ->and($entryPhoto['caption'])->toBe('Along the river');

    // On the cross-entry /photos wall, the caption is deliberately the entry
    // title, not the photo's own caption: across photos from everywhere, the
    // useful label is which entry each came from. `alt` still carries the
    // photo's own description in both shapes.
    expect($galleryPhoto['alt'])->toBe('Clare mid-stride')
        ->and($galleryPhoto['caption'])->toBe('Sunday long run')
        ->and($galleryPhoto['caption'])->not->toBe('Along the river');
});
