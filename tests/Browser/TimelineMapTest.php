<?php

use App\Models\Fuel;

it('shows the stored fuel map image on the timeline', function () {
    $fuel = Fuel::factory()->create(['station_name' => 'Test Garage', 'occurred_at' => now()]);
    $fuel->addMediaFromString('PNG')->usingFileName('m.png')->toMediaCollection('map');

    $page = visit('/');

    // Assert the rendered <img>, not the props JSON: the fuel card's stored map
    // image is present, served from the public disk's /storage symlink.
    $page->assertPresent('img[src*="/storage"]');
});
