<?php

use App\Enums\ExportFormat;

it('gives every format a distinct extension and content type', function () {
    $extensions = array_column(ExportFormat::cases(), 'value');
    $types = array_map(fn (ExportFormat $f): string => $f->contentType(), ExportFormat::cases());

    expect($extensions)->toHaveCount(7)
        ->and(array_unique($extensions))->toHaveCount(7)
        ->and(array_unique($types))->toHaveCount(7)
        ->and(ExportFormat::Json->contentType())->toBe('application/json; charset=utf-8')
        ->and(ExportFormat::Yaml->contentType())->toBe('text/yaml; charset=utf-8')
        ->and(ExportFormat::Mf2->contentType())->toBe('application/mf2+json; charset=utf-8')
        ->and(ExportFormat::GeoJson->contentType())->toBe('application/geo+json; charset=utf-8')
        ->and(ExportFormat::GeoJson->value)->toBe('geojson');
});

it('declares a charset on every format, so a browser never guesses the encoding', function () {
    foreach (ExportFormat::cases() as $format) {
        expect($format->contentType())->toContain('charset=utf-8');
    }
});

it('builds a route constraint from its own cases', function () {
    expect(ExportFormat::pattern())->toBe('json|yaml|txt|md|mf2|ics|geojson');
});
