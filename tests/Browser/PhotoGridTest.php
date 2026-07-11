<?php

use App\Models\Note;
use Illuminate\Support\Facades\Storage;

/** A solid-colour JPEG at a given size, so the media pipeline generates a card conversion. */
function photoGridJpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 120, 120, 120));
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

it('fills each masonry tile with the photo, leaving no dead space for landscape shots', function () {
    config(['queue.default' => 'sync']); // run media conversions inline so the srcset exists
    Storage::fake('public');

    $note = Note::factory()->create([
        'content' => 'Month photos',
        'occurred_at' => '2019-05-15 12:00:00',
    ]);
    $note->addMediaFromString(photoGridJpeg(1600, 1200))->usingFileName('landscape.jpg')->toMediaCollection('photos');
    $note->addMediaFromString(photoGridJpeg(1200, 1600))->usingFileName('portrait.jpg')->toMediaCollection('photos');

    $page = visit('/2019/05');

    // Every tile's image covers the tile (object-fit: cover) and fills its height
    // (the only gap is the 1px top+bottom tile border), so a landscape photo can't
    // sit in a too-tall tile with grey space beneath it.
    $page->assertScript(
        "[...document.querySelectorAll('ul.grid img')].every((img) => {
            const tile = img.closest('li');
            const gap = tile.getBoundingClientRect().height - img.getBoundingClientRect().height;
            return getComputedStyle(img).objectFit === 'cover' && gap <= 4;
        })",
        true,
    );
});
