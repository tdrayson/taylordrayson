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

it('sizes skeleton tiles like real photos rather than as tall columns', function () {
    config(['queue.default' => 'sync']);
    Storage::fake('public');

    $note = Note::factory()->create(['content' => 'A photo', 'occurred_at' => '2019-05-15 12:00:00']);
    $image = imagecreatetruecolor(1600, 1200);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $note->addMediaFromString($bytes)->usingFileName('landscape.jpg')->toMediaCollection('photos');

    // A placeholder tile is a stand-in for a photo, so its height has to stay in
    // photo proportions. Spans were once hardcoded and rendered ~3x too tall.
    visit('/photos')->assertScript(
        "(() => {
            const tiles = [...document.querySelectorAll('.animate-pulse')];
            if (!tiles.length) return 'no skeleton rendered';
            return tiles.every((tile) => {
                const box = tile.getBoundingClientRect();
                const ratio = box.height / box.width;
                return ratio > 0.4 && ratio < 2.2;
            });
        })()",
        true,
    );
});
