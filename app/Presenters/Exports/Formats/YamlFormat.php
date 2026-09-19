<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Enums\ExportFormat;
use Symfony\Component\Yaml\Yaml;

/** The same object as JSON, in YAML. */
final class YamlFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Yaml;
    }

    public function render(ExportData $data, array $trail): string
    {
        return Yaml::dump([...$data->toArray(), 'formats' => $trail], 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
