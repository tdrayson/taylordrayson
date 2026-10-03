<?php

namespace App\Presenters\Heads;

use App\Data\ExportData;
use App\Data\Head\LinkTagData;
use App\Presenters\Exports\Formats\Format;
use App\Presenters\Exports\Formats\Formats;

/** The rel=alternate links advertising every format an export can be rendered as. */
final class FormatLinks
{
    /**
     * @param  string|null  $title  The page title the links are named after, or null for the site name.
     * @return list<LinkTagData>
     */
    public static function for(ExportData $export, ?string $title): array
    {
        $name = $title ?? config('identity.name');

        return array_values(array_map(
            fn (Format $format): LinkTagData => new LinkTagData(
                rel: 'alternate',
                href: $export->url.'.'.$format->format()->value,
                type: $format->format()->contentType(),
                title: "{$name} ({$format->format()->label()})",
            ),
            Formats::for($export),
        ));
    }
}
