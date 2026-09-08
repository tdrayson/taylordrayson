<?php

use App\Models\Note;
use Illuminate\Support\Facades\Storage;

it('replaces the masonry skeleton with the real grid once the deferred photos land', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $note = Note::factory()->create(['content' => 'A photo', 'occurred_at' => '2019-05-15 12:00:00']);
    $image = imagecreatetruecolor(1600, 1200);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $note->addMediaFromString($bytes)->usingFileName('landscape.jpg')->toMediaCollection('photos');

    $page = visit('/photos');

    // The images live in PhotoGrid's ul, never in the skeleton's div, so this
    // can only pass once the deferred prop has resolved and swapped it in.
    $page->assertScript("document.querySelectorAll('ul.grid img').length > 0", true);

    // And no placeholder is left behind underneath it.
    $page->assertScript(
        "[...document.querySelectorAll('.animate-pulse')].filter((el) => el.offsetParent !== null).length",
        0,
    );
});
