<?php

use App\Models\Activity;

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
