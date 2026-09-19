<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportLink;

/**
 * A This Week With episode printed as its rundown. Reads the export only:
 * every string here is a field's display value, so this layout cannot drift
 * from the data.
 */
final class ThisWeekWithSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            Sheet::centre($this->value($data, 'episode'), self::WIDTH),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->wrappedBlock($this->value($data, 'topic')),
            '',
            ...$this->maybeRow($data, 'DURATION', 'duration'),
            ...$this->linkRow($data, 'LISTEN', 'listen'),
            ...$this->linkRow($data, 'WATCH', 'watch'),
        ]);
    }

    /**
     * A link's URL as a row, dropped entirely rather than printed empty
     * when the episode carries no link for that key.
     *
     * @return list<string>
     */
    private function linkRow(ExportData $data, string $label, string $key): array
    {
        $link = $this->link($data, $key);

        return $link === null ? [] : [Sheet::row($label, $link->url, self::WIDTH)];
    }

    private function link(ExportData $data, string $key): ?ExportLink
    {
        foreach ($data->links as $link) {
            if ($link->key === $key) {
                return $link;
            }
        }

        return null;
    }

    /**
     * A label/value row, dropped entirely rather than printed empty when
     * the field carries no value.
     *
     * @return list<string>
     */
    private function maybeRow(ExportData $data, string $label, string $key): array
    {
        $field = $data->field($key);

        return $field === null ? [] : [Sheet::row($label, $field->display, self::WIDTH)];
    }

    /**
     * The topic wrapped to the sheet width, so a long rundown reads as a
     * column rather than one very long line.
     *
     * @return list<string>
     */
    private function wrappedBlock(string $value): array
    {
        return $value === '' ? [] : Sheet::wrap($value, self::WIDTH);
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
