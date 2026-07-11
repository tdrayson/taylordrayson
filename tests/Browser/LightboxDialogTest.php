<?php

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

/** A real JPEG, so the media library conversion pipeline can process it. */
function lightboxPhotoJpegBytes(int $seed): string
{
    $image = imagecreatetruecolor(800, 600);
    // Tint each image differently so the two photos are distinguishable.
    imagefilledrectangle($image, 0, 0, 800, 600, imagecolorallocate($image, $seed * 40, 0, 0));
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

beforeEach(function () {
    Storage::fake('public');
});

it('opens the lightbox on a photo click, navigates with arrows, and closes on escape', function () {
    $event = Event::factory()->create([
        'name' => 'Gallery Gig',
        'occurred_at' => '2022-06-02 19:00:00',
        'venue_name' => 'Gallery Venue',
        'city' => 'London',
    ]);
    $event->addMediaFromString(lightboxPhotoJpegBytes(1))->usingFileName('p1.jpg')->toMediaCollection('photos');
    $event->addMediaFromString(lightboxPhotoJpegBytes(2))->usingFileName('p2.jpg')->toMediaCollection('photos');

    $page = visit($event->url());

    // Dialog closed initially: no rendered dialog element.
    $page->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);

    // Open the lightbox from the first thumbnail.
    $page->click('[aria-label="View photo 1"]')
        ->assertScript("!!document.querySelector('[role=\"dialog\"]')", true);

    // Two photos render a three-slide carousel (previous, current, next); the
    // centred (currently displayed) image is the middle one.
    $currentSrc = $page->script("document.querySelectorAll('[role=\"dialog\"] img')[1].src");

    // Step forward with the keyboard; the slide animation settles after ~300ms.
    $page->keys('[role="dialog"]', 'ArrowRight')->wait(0.6);

    $page->assertScript(
        "document.querySelectorAll('[role=\"dialog\"] img')[1].src !== '{$currentSrc}'",
        true,
    );

    // Escape closes the dialog.
    $page->keys('[role="dialog"]', 'Escape')
        ->assertScript("document.querySelector('[role=\"dialog\"]') === null", true);
});
