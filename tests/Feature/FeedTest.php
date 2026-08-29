<?php

use App\Models\Activity;
use App\Models\Checkin;

it('serves the atom feed', function () {
    Activity::factory()->create([
        'name' => 'Feed Test Run',
        'occurred_at' => now(),
    ]);

    $this->get('/feed')
        ->assertOk()
        ->assertSee('Feed Test Run')
        ->assertSee('Taylor Drayson');
});

it('orders feed entries newest first', function () {
    Activity::factory()->create(['name' => 'Older Entry', 'occurred_at' => now()->subDays(2)]);
    Activity::factory()->create(['name' => 'Newer Entry', 'occurred_at' => now()->subDay()]);

    $this->get('/feed')->assertSeeInOrder(['Newer Entry', 'Older Entry']);
});

it('serves rss and json feeds', function () {
    Activity::factory()->create([
        'name' => 'Multi Format Run',
        'occurred_at' => now(),
    ]);

    $this->get('/feed/rss')->assertOk()->assertSee('Multi Format Run');
    $this->get('/feed/json')->assertOk()->assertSee('Multi Format Run');
});

// The summary is the standalone description, not the card subtitle: a check-in
// with no note has no subtitle at all, and would reach a reader as a bare title.
it('summarises a check-in in the feed even though its card carries no subtitle', function () {
    Checkin::factory()->create([
        'venue_name' => 'Blue Bottle',
        'category' => 'Coffee Shop',
        'city' => 'London',
        'description' => null,
        'occurred_at' => now(),
    ]);

    $this->get('/feed')->assertOk()->assertSee('I checked in at Blue Bottle', false);
});
