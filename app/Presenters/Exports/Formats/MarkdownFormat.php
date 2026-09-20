<?php

namespace App\Presenters\Exports\Formats;

use App\Data\ExportData;
use App\Data\ExportLink;
use App\Enums\ExportFormat;
use App\Support\PortableText;
use Symfony\Component\Yaml\Yaml;

/**
 * The export as a markdown document: front matter carrying the fields, the
 * title, the body, then the links.
 */
final class MarkdownFormat extends Format
{
    public function format(): ExportFormat
    {
        return ExportFormat::Md;
    }

    public function render(ExportData $data): string
    {
        $parts = [$this->frontMatter($data), "# {$data->title}"];

        if ($data->summary !== null) {
            $parts[] = $data->summary;
        }

        $body = is_string($data->body) ? $data->body : PortableText::markdown($data->body);

        if ($body !== '') {
            $parts[] = $body;
        }

        if ($data->links !== []) {
            $parts[] = "## See also\n\n".implode("\n", array_map(
                fn (ExportLink $link): string => "- {$link->label}: [{$link->title}]({$link->url})",
                $data->links,
            ));
        }

        return implode("\n\n", $parts)."\n";
    }

    private function frontMatter(ExportData $data): string
    {
        $matter = array_filter([
            'type' => $data->typeValue(),
            'url' => $data->url,
            'title' => $data->title,
            'date' => $data->occurred?->iso,
        ], fn (?string $value): bool => $value !== null);

        foreach ($data->fields as $field) {
            $matter[$field->label] = $field->display;
        }

        return "---\n".Yaml::dump($matter, 2, 2).'---';
    }
}
