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
    case Ics = 'ics';
    case GeoJson = 'geojson';

    /**
     * A `text/*` subtype for `.yaml`, not `application/*`: a browser renders
     * any `text/*` response inline rather than downloading it, the same
     * reason `.md` is `text/markdown` rather than `application/markdown`.
     * Neither has a consumer that dispatches on its type, so being readable
     * in a browser is the whole point.
     *
     * Every entry declares `charset=utf-8` explicitly, even where a spec
     * (RFC 8259 for JSON) already implies it: without it a browser guesses
     * Latin-1 and mangles every non-ASCII byte the export writes out.
     */
    public function contentType(): string
    {
        return match ($this) {
            self::Json => 'application/json; charset=utf-8',
            self::Yaml => 'text/yaml; charset=utf-8',
            self::Txt => 'text/plain; charset=utf-8',
            self::Md => 'text/markdown; charset=utf-8',
            self::Mf2 => 'application/mf2+json; charset=utf-8',
            self::Ics => 'text/plain; charset=utf-8',
            self::GeoJson => 'application/geo+json; charset=utf-8',
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
            self::Ics => 'Calendar',
            self::GeoJson => 'GeoJSON',
        };
    }

    /** The route constraint, built from cases so a new format cannot be missed. */
    public static function pattern(): string
    {
        return implode('|', array_column(self::cases(), 'value'));
    }
}
