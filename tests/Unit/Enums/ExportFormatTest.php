<?php

use App\Enums\ExportFormat;

it('gives every format a distinct extension', function () {
    $extensions = array_column(ExportFormat::cases(), 'value');

    expect($extensions)->toHaveCount(7)
        ->and(array_unique($extensions))->toHaveCount(7)
        ->and(ExportFormat::Json->contentType())->toBe('application/json; charset=utf-8')
        ->and(ExportFormat::Yaml->contentType())->toBe('text/yaml; charset=utf-8')
        ->and(ExportFormat::Mf2->contentType())->toBe('application/mf2+json; charset=utf-8')
        ->and(ExportFormat::GeoJson->contentType())->toBe('application/geo+json; charset=utf-8')
        ->and(ExportFormat::GeoJson->value)->toBe('geojson');
});

// Every entry is a past event, so handing off to a calendar app has little
// use; rendering the VEVENT markup like the other formats is the point.
it('renders .ics as plain text instead of a calendar download', function () {
    expect(ExportFormat::Ics->contentType())->toBe('text/plain; charset=utf-8');
});

it('declares a charset on every format, so a browser never guesses the encoding', function () {
    foreach (ExportFormat::cases() as $format) {
        expect($format->contentType())->toContain('charset=utf-8');
    }
});

it('builds a route constraint from its own cases', function () {
    expect(ExportFormat::pattern())->toBe('json|yaml|txt|md|mf2|ics|geojson');
});
