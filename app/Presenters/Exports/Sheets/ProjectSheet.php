<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\ExportData;
use App\Data\ExportLink;

/**
 * A project printed as its package manifest. Reads the export only: every
 * string here is a field's display value or a link's own url, so this
 * layout cannot drift from the data.
 */
final class ProjectSheet
{
    private const WIDTH = 46;

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            ...$this->centredBlock($this->value($data, 'project')),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->maybeRow($data, 'STAGE', 'stage'),
            ...$this->linkRow($data, 'SITE', 'site'),
            ...$this->linkRow($data, 'CODE', 'code'),
        ]);
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
     * A label/url row, dropped when there is no link or the url is too long
     * to share the row: printing it anyway would hard-wrap it mid-character,
     * as an earlier batch did to a podcast link.
     *
     * @return list<string>
     */
    private function linkRow(ExportData $data, string $label, string $key): array
    {
        $link = $this->link($data, $key);

        if ($link === null || mb_strlen($label) + 1 + mb_strlen($link->url) > self::WIDTH) {
            return [];
        }

        return [Sheet::row($label, $link->url, self::WIDTH)];
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
     * A value centred, wrapped across as many lines as it needs, so a long
     * project name is not clipped mid-word.
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

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
