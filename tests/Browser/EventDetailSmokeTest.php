<?php

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

/** A real JPEG, so the media library conversion pipeline can process it. */
function smokeEventCoverJpegBytes(): string
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

it('renders an event with a photo, map and google link without JS errors', function () {
    $event = Event::factory()->create([
        'name' => 'Smoke Gig',
        'occurred_at' => '2022-06-02 19:00:00',
        'venue_name' => 'Smoke Venue',
        'city' => 'London',
        'latitude' => 51.5,
        'longitude' => -0.12,
        'meta' => ['address' => 'Smoke Venue, London'],
    ]);
    $event->addMediaFromString(smokeEventCoverJpegBytes())->usingFileName('c.jpg')->toMediaCollection('cover');

    $page = visit($event->url());

    $page->assertNoJavaScriptErrors()
        ->assertSee('Smoke Gig')
        ->assertSee('View on Google Maps');
});
