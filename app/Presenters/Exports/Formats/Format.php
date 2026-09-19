<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Enums\ExportFormat;

/**
 * One rendering of an export. A format that needs more than fields and links
 * overrides supports() to name the aspect it requires, so availability is
 * derived from the payload rather than declared twice.
 */
abstract class Format
{
    abstract public function format(): ExportFormat;

    /**
     * @param  array<string, string>  $trail  The other formats for this resource, extension to absolute URL.
     */
    abstract public function render(ExportData $data, array $trail): string;

    /**
     * Must be side-effect-free and cheap: Formats::for() calls it once per
     * format per request, and later formats do aspect lookups here.
     */
    public function supports(ExportData $data): bool
    {
        return true;
    }
}
