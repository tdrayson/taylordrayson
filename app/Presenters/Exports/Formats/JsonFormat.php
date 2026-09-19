<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Enums\ExportFormat;

/** The export as JSON: every field with both its display string and its raw value. */
final class JsonFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Json;
    }

    public function render(ExportData $data): string
    {
        return json_encode(
            $data->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
