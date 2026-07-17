<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(public_path('logos/brands/bp.png'));
});

it('shows the brand logo image on a fuel entry page', function () {
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/bp.png'), 'x');

    $fuel = Fuel::factory()->create([
        'brand' => 'BP',
        'station_name' => 'BP Cobham',
        'latitude' => 51.3,
        'longitude' => -0.1,
    ]);

    $page = visit($fuel->url());

    $page->assertPresent('img[src="/logos/brands/bp.png"]');
});
