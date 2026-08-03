<?php

use App\Models\Event;
use App\Presenters\CardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('carries both the location map and photos on the card, like a check-in', function () {
    Storage::fake('public');

    $event = Event::factory()->create(['occurred_at' => now()]);
    $event->addMediaFromString(fakeJpeg())->usingFileName('photo.jpg')->toMediaCollection('photos');
    $event->addMediaFromString(fakeJpeg())->usingFileName('map.png')->toMediaCollection('map');

    $meta = CardPresenter::for($event->fresh())->meta->toArray();

    // Both present so the feed renders the map + first photo together (and the
    // mobile swipe carousel), rather than the old either/or.
    expect($meta['map'])->not->toBeNull()
        ->and($meta['photos'])->toHaveCount(1);
});
