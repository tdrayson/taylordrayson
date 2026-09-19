<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Support\PortableText;

/**
 * A note printed as its index card. Reads the export only: the body is the
 * export's Portable Text flattened to plain prose, so this cannot drift
 * from the model.
 */
final class NoteSheet
{
    private const WIDTH = 46;

    private const INNER = self::WIDTH - 4;

    public function render(ExportData $data): string
    {
        return Sheet::join([Sheet::box($this->wrappedLines($this->bodyText($data)), self::WIDTH)]);
    }

    private function bodyText(ExportData $data): string
    {
        return is_string($data->body) ? $data->body : PortableText::text($data->body);
    }

    /**
     * The body wrapped to the card's inner width, so a long note reads as a
     * column rather than one very long line.
     *
     * @return list<string>
     */
    private function wrappedLines(string $value): array
    {
        // A pasted link overflows its line rather than being cut mid-character.
        return $value === '' ? [] : Sheet::wrap($value, self::INNER, cut: false);
    }
}
