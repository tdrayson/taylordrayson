<?php

use App\Models\Activity;

it('renders the route scrub dot for an activity with a track', function () {
    $activity = Activity::factory()->create([
        'type' => 'run',
        'occurred_at' => now(),
        'meta' => ['polyline' => 'ki~mHvfyLPKLA'],
        'track' => [
            ['time' => now()->format('Y-m-d H:i:s'), 'lat' => 51.50, 'lng' => -0.10],
            ['time' => now()->addSecond()->format('Y-m-d H:i:s'), 'lat' => 51.51, 'lng' => -0.11],
        ],
    ]);

    $page = visit($activity->url());

    // Rendered DOM: the scrub dot marker element exists (hidden until hover, but present).
    $page->assertPresent('[data-testid="route-dot"]');
});
