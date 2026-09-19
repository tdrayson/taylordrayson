<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Enums\ExportFormat;

/** Every format, and which of them a given export can actually be rendered as. */
final class Formats
{
    /**
     * @return list<Format>
     */
    public static function all(): array
    {
        return [
            new JsonFormat,
            new YamlFormat,
            new MarkdownFormat,
            new SqlFormat,
            new GeoJsonFormat,
        ];
    }

    /**
     * @return array<string, Format> Keyed by extension, supported ones only.
     */
    public static function for(ExportData $data): array
    {
        $supported = [];

        foreach (self::all() as $format) {
            if ($format->supports($data)) {
                $supported[$format->format()->value] = $format;
            }
        }

        return $supported;
    }

    public static function find(ExportData $data, ExportFormat $format): ?Format
    {
        return self::for($data)[$format->value] ?? null;
    }
}
