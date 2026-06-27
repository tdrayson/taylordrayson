<?php

use App\Support\StaticMap;

it('returns null for an empty route polyline', function () {
    expect(StaticMap::route(null))->toBeNull();
    expect(StaticMap::route(''))->toBeNull();
});

it('builds a route map url embedding the polyline overlay', function () {
    $url = StaticMap::route('abcdefg', '2e9e6a');

    expect($url)
        ->toContain('api.mapbox.com/styles/v1/mapbox/light-v11/static/')
        ->toContain('path-5+2e9e6a-0.85(')
        ->toContain('/auto/1200x630@2x')
        ->toContain('padding=64');
});

it('builds a marker map url centred on the coordinate', function () {
    $url = StaticMap::marker(-0.0726, 51.337, '2e9e8c');

    expect($url)
        ->toContain('pin-l+2e9e8c(-0.0726,51.337)')
        ->toContain(',14/1200x630@2x');
});

it('returns null for an arc with a missing coordinate', function () {
    expect(StaticMap::arc(null, 51.0, 0.0, 40.0))->toBeNull();
});

it('builds a flight arc with dashes, an arrowhead and ring markers', function () {
    // London Heathrow -> New York JFK.
    $url = StaticMap::arc(-0.4543, 51.4700, -73.7781, 40.6413, '209fdf');

    expect($url)
        ->toContain('api.mapbox.com/styles/v1/mapbox/light-v11/static/')
        ->toContain('path-5+209fdf-1(')            // dashed segments
        ->toContain('path-1+209fdf-1+209fdf-1(')   // filled arrowhead
        ->toContain('path-6+209fdf-1+ffffff-1(')   // white-ring airport markers
        ->toContain('@2x');
});
