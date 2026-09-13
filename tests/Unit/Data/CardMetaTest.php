<?php

use App\Data\AirlineData;
use App\Data\CardMeta;
use App\Data\MediaData;
use App\Data\PhotoData;
use App\Data\RouteData;
use App\Data\RoutePoint;
use App\Data\SegmentData;
use App\Support\PortableText;

it('serialises empty meta as an empty array', function () {
    expect(CardMeta::empty()->toArray())->toBe([]);
});

it('serialises activity meta with only polyline, photos, map, mapDark', function () {
    $photo = PhotoData::gallery(1, 'src.webp', null, 'full.webp', null, null, 1.1, 2.2);
    $meta = CardMeta::activity('poly', [$photo], 'map.png', 'mapdark.png');

    expect($meta->toArray())->toBe([
        'polyline' => 'poly',
        'photos' => [$photo->toArray()],
        'map' => 'map.png',
        'mapDark' => 'mapdark.png',
    ]);
});

it('serialises sleep meta with only segments', function () {
    $segment = new SegmentData('Awake', 'awake', 120);
    $meta = CardMeta::sleep([$segment]);

    expect($meta->toArray())->toBe(['segments' => [$segment->toArray()]]);
});

it('serialises event meta with only photos, map, mapDark', function () {
    $meta = CardMeta::event([], null, null);

    expect($meta->toArray())->toBe(['photos' => [], 'map' => null, 'mapDark' => null]);
});

it('serialises media meta with only media', function () {
    $media = MediaData::withoutSrcset(1, 'Title', null, null, null, null, null, '/x');
    $meta = CardMeta::media($media);

    expect($meta->toArray())->toBe(['media' => $media->toArray()]);
});

it('serialises route meta with only route, map, mapDark', function () {
    $route = new RouteData(
        origin: new RoutePoint('LHR', 'London', 'Heathrow', 51.5, -0.45),
        destination: new RoutePoint('JFK', 'New York', 'JFK', 40.6, -73.7),
        depart: '2026-01-01T09:00',
        arrive: '2026-01-01T17:00',
        distance: 3450,
        duration: 26700,
        airline: new AirlineData('easyJet UK', '/icon.png', 'U2 8821'),
    );

    $meta = CardMeta::route($route, 'map.png', null);

    expect($meta->toArray())->toBe(['route' => $route->toArray(), 'map' => 'map.png', 'mapDark' => null]);
});

it('serialises checkin meta with photos, map, mapDark, address, category', function () {
    $meta = CardMeta::checkin([], 'map.png', 'mapdark.png', 'High Street, London', 'Coffee Shop');

    expect($meta->toArray())->toBe([
        'photos' => [],
        'map' => 'map.png',
        'mapDark' => 'mapdark.png',
        'address' => 'High Street, London',
        'category' => 'Coffee Shop',
    ]);
});

it('serialises article photos meta with only photos', function () {
    $photo = PhotoData::cover(1, 'src.webp', null, 'full.webp', null, null);
    $meta = CardMeta::photos([$photo]);

    expect($meta->toArray())->toBe(['photos' => [$photo->toArray()]]);
});

it('serialises note meta with only body, photos, previews, favicons', function () {
    $document = PortableText::fromPlainText('Some note text');

    $meta = CardMeta::note($document, []);

    expect($meta->toArray())->toBe([
        'body' => $document,
        'photos' => [],
        'previews' => [],
        'favicons' => [],
    ]);
});
