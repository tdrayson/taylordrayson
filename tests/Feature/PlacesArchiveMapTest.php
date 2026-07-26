<?php

use App\Models\Checkin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('exposes deduped check-in places for the archive overview map', function () {
    // Two distinct places, plus a duplicate of the first venue's coordinates
    // that must collapse to a single map point.
    Checkin::factory()->create(['latitude' => 51.5074, 'longitude' => -0.1278, 'venue_name' => 'A']);
    Checkin::factory()->create(['latitude' => 51.5074, 'longitude' => -0.1278, 'venue_name' => 'A again']);
    Checkin::factory()->create(['latitude' => 52.4862, 'longitude' => -1.8904, 'venue_name' => 'B']);

    $this->get('/places')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Archive')->has('map', 2));
});
