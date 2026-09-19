<?php

use App\Enums\ExportFormat;

it('gives every format a distinct extension and content type', function () {
    $extensions = array_column(ExportFormat::cases(), 'value');
    $types = array_map(fn (ExportFormat $f): string => $f->contentType(), ExportFormat::cases());

    expect($extensions)->toHaveCount(8)
        ->and(array_unique($extensions))->toHaveCount(8)
        ->and(array_unique($types))->toHaveCount(8)
        ->and(ExportFormat::Json->contentType())->toBe('application/json')
        ->and(ExportFormat::GeoJson->value)->toBe('geojson');
});

it('builds a route constraint from its own cases', function () {
    expect(ExportFormat::pattern())->toBe('json|yaml|txt|md|mf2|sql|ics|geojson');
});
