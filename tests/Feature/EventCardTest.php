<?php

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

/** A real JPEG, so the media library conversion pipeline can process it. */
function eventCoverJpegBytes(): string
{
    $image = imagecreatetruecolor(800, 600);
    ob_start();
    imagejpeg($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

beforeEach(function () {
    Storage::fake('public');
});

it('includes a photo and multi-day range on the event card payload', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2022-06-02 09:00:00',
        'ends_at' => '2022-06-04 18:00:00',
    ]);
    $event->addMediaFromString(eventCoverJpegBytes())->usingFileName('c.jpg')->toMediaCollection('cover');

    $this->get('/2022/06/03')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('items.0.photos', 1)
            ->where('items.0.range.days', 3));
});
