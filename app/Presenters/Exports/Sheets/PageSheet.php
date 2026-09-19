<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Support\PortableText;

/**
 * A page printed as its typeset page: title, a rule, then the body, the
 * same treatment as an article. Reads the export only: title comes from
 * the export itself, the body from its Portable Text.
 */
final class PageSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            ...$this->centredBlock($data->title),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->wrappedBlock($this->bodyText($data)),
        ]);
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
        // A pasted link overflows its line rather than being cut mid-character.
        return $value === '' ? [] : Sheet::wrap($value, self::WIDTH, cut: false);
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
