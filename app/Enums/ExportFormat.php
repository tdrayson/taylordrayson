<?php

namespace App\Enums;

/**
 * One output format an export can be rendered as. Backed values are the URL
 * extension, so the route constraint and the trail are both built from cases.
 */
enum ExportFormat: string
{
    case Json = 'json';
    case Yaml = 'yaml';
    case Txt = 'txt';
    case Md = 'md';
    case Mf2 = 'mf2';
    case Sql = 'sql';
    case Ics = 'ics';
    case GeoJson = 'geojson';

    public function contentType(): string
    {
        return match ($this) {
            self::Json => 'application/json',
            self::Yaml => 'application/yaml',
            self::Txt => 'text/plain; charset=utf-8',
            self::Md => 'text/markdown; charset=utf-8',
            self::Mf2 => 'application/mf2+json',
            self::Sql => 'application/sql',
            self::Ics => 'text/calendar; charset=utf-8',
            self::GeoJson => 'application/geo+json',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Json => 'JSON',
            self::Yaml => 'YAML',
            self::Txt => 'Plain text',
            self::Md => 'Markdown',
            self::Mf2 => 'Microformats',
            self::Sql => 'SQL',
            self::Ics => 'Calendar',
            self::GeoJson => 'GeoJSON',
        };
    }

    /** The one line the footer menu shows beside the extension. */
    public function purpose(): string
    {
        return match ($this) {
            self::Json => 'Structured data',
            self::Yaml => 'The same, friendlier',
            self::Txt => 'Printed',
            self::Md => 'The source',
            self::Mf2 => 'Microformats',
            self::Sql => 'The INSERT that made it',
            self::Ics => 'Add to calendar',
            self::GeoJson => 'The route',
        };
    }

    /** The route constraint, built from cases so a new format cannot be missed. */
    public static function pattern(): string
    {
        return implode('|', array_column(self::cases(), 'value'));
    }
}
