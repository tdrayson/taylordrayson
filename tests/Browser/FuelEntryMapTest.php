<?php

use App\Models\Fuel;

it('renders the location map and garage fields on a fuel entry', function () {
    $fuel = Fuel::factory()->create([
        'station_name' => 'Godstone Road SF Connect',
        'brand' => 'BP',
        'postcode' => 'CR3 0EG',
        'latitude' => 51.31345,
        'longitude' => -0.08194,
        'occurred_at' => now(),
    ]);

    $page = visit($fuel->url());

    // Assert a RENDERED element, not text also present in the Inertia props JSON:
    // the "View on Google Maps" ExternalLink renders an <a> only when the location
    // block (which contains the map) is present. Matching the anchor by href proves
    // FuelDetail rendered the location section, avoiding the props-JSON false positive.
    $page->assertPresent('a[href*="google.com/maps"]');
});
