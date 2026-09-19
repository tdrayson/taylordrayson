<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Support\PortableText;

/**
 * An article printed as its typeset page. Reads the export only: title and
 * summary come from the export itself, the body from its Portable Text, so
 * this layout cannot drift from the model.
 */
final class ArticleSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            ...$this->centredBlock($data->title),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->wrappedBlock($data->summary ?? ''),
            ...$this->bodyBlock($data),
        ]);
    }

    /**
     * The body, on a blank line of its own beneath the summary, dropped
     * entirely when there is nothing to print.
     *
     * @return list<string>
     */
    private function bodyBlock(ExportData $data): array
    {
        $body = $this->wrappedBlock($this->bodyText($data));

        return $body === [] ? [] : ['', ...$body];
    }

    private function bodyText(ExportData $data): string
    {
        return is_string($data->body) ? $data->body : PortableText::text($data->body);
    }

    /**
     * A value wrapped to the sheet's width, so a long paragraph reads as a
     * column rather than one very long line.
     *
     * @return list<string>
     */
    private function wrappedBlock(string $value): array
    {
        return $value === '' ? [] : Sheet::wrap($value, self::WIDTH);
    }

    /**
     * A value centred, wrapped across as many lines as it needs, so a long
     * title is not clipped mid-word.
     *
     * @return list<string>
     */
    private function centredBlock(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map(fn (string $line): string => Sheet::centre($line, self::WIDTH), Sheet::wrap($value, self::WIDTH));
    }
}
