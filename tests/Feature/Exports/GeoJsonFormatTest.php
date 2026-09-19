<?php

use App\Data\ExportData;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Formats\GeoJsonFormat;

it('renders a flight route as a linestring, longitude first', function () {
    $data = ExportPresenter::for(krkToLgw());
    $geo = json_decode(Formats::find($data, ExportFormat::GeoJson)->render($data), true);
    $coordinates = $geo['geometry']['coordinates'];

    expect($geo['type'])->toBe('Feature')
        ->and($geo['geometry']['type'])->toBe('LineString')
        ->and($coordinates[0])->toBe([19.7848, 50.077702])
        ->and(end($coordinates))->toBe([-0.192089, 51.148771])
        ->and(count($coordinates))->toBeGreaterThan(2)
        ->and($geo['properties']['title'])->not->toBeEmpty();
});

it('is unavailable without a geometry aspect', function () {
    $data = ExportPresenter::for(krkToLgw());
    $withoutGeometry = new ExportData(
        type: $data->type, url: $data->url, title: $data->title, summary: null,
        occurred: null, fields: $data->fields, links: [],
    );

    expect((new GeoJsonFormat)->supports($withoutGeometry))->toBeFalse()
        ->and(array_key_exists('geojson', Formats::for($withoutGeometry)))->toBeFalse();
});

it('refuses geojson for a locked export even if it carries a geometry aspect', function () {
    $data = ExportPresenter::for(krkToLgw());
    $locked = new ExportData(
        type: $data->type, url: $data->url, title: $data->title, summary: null,
        occurred: null, fields: $data->fields, links: [], aspects: $data->aspects, locked: true,
    );

    expect((new GeoJsonFormat)->supports($locked))->toBeFalse();
});

it('refuses geojson for a note with no geometry aspect at all', function () {
    $note = new ExportData(
        type: TimelineType::Note, url: 'https://example.test/x', title: 'A note',
        summary: null, occurred: null, fields: [], links: [],
    );

    expect((new GeoJsonFormat)->supports($note))->toBeFalse();
});
