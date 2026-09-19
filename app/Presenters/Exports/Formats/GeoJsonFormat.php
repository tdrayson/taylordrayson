<?php

namespace App\Presenters\Exports\Formats;

use App\Data\Aspects\Geometry;
use App\Data\ExportData;
use App\Enums\ExportFormat;

/** Where the entry happened, as a GeoJSON Feature. Available only with a Geometry aspect. */
final class GeoJsonFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::GeoJson;
    }

    public function supports(ExportData $data): bool
    {
        return ! $data->locked && $data->aspect(Geometry::class) !== null;
    }

    public function render(ExportData $data, array $trail): string
    {
        $properties = [
            'type' => $data->typeValue(),
            'title' => $data->title,
            'url' => $data->url,
        ];

        foreach ($data->fields as $field) {
            $properties[$field->key] = $field->display;
        }

        return json_encode([
            'type' => 'Feature',
            'geometry' => $data->aspect(Geometry::class)->toArray(),
            'properties' => [...$properties, 'formats' => $trail],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
