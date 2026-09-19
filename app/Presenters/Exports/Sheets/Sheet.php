<?php

namespace App\Presenters\Exports\Sheets;

/**
 * Drawing primitives shared by every typed sheet: rules, centred text, aligned
 * rows, dotted leaders, boxes and bars. Measured in characters rather than
 * bytes, because an accented place name would otherwise skew its own row.
 */
final class Sheet
{
    public const WIDTH = 46;

    public static function rule(int $width = self::WIDTH, string $char = '-'): string
    {
        return str_repeat($char, $width);
    }

    public static function centre(string $text, int $width = self::WIDTH): string
    {
        $text = self::clip($text, $width);
        $left = intdiv($width - mb_strlen($text), 2);

        return str_repeat(' ', $left).$text.str_repeat(' ', $width - $left - mb_strlen($text));
    }

    /** Label left, value hard right, spaces between. */
    public static function row(string $label, string $value, int $width = self::WIDTH): string
    {
        return self::fill($label, $value, ' ', $width);
    }

    /** The same, with dots between, for a receipt. */
    public static function leader(string $label, string $value, int $width = self::WIDTH): string
    {
        return self::fill($label, $value, '.', $width);
    }

    /**
     * @param  list<string>  $lines
     */
    public static function box(array $lines, int $width = self::WIDTH): string
    {
        $inner = $width - 4;
        $out = ['+'.str_repeat('-', $width - 2).'+'];

        foreach ($lines as $line) {
            $out[] = '| '.self::pad(self::clip($line, $inner), $inner).' |';
        }

        $out[] = '+'.str_repeat('-', $width - 2).'+';

        return implode("\n", $out);
    }

    /** A proportional bar, for a sleep stage or a heart rate against its max. */
    public static function bar(float $fraction, int $width = 20): string
    {
        $filled = (int) round(max(0.0, min(1.0, $fraction)) * $width);

        return str_repeat('#', $filled).str_repeat('.', $width - $filled);
    }

    public static function heading(string $text, int $width = self::WIDTH): string
    {
        return mb_strtoupper(self::clip($text, $width))."\n".self::rule($width, '=');
    }

    /**
     * Wrap a body to the sheet's width, so an article or a note prints as a
     * column rather than one very long line.
     *
     * @return list<string>
     */
    public static function wrap(string $text, int $width = self::WIDTH): array
    {
        return explode("\n", wordwrap($text, $width, "\n", true));
    }

    /**
     * Both ends of a row, separated by enough fill to reach the width. The
     * value wins when the two cannot both fit: it is the fact, the label is
     * only what it is called.
     */
    private static function fill(string $label, string $value, string $char, int $width): string
    {
        $value = self::clip($value, $width);
        $remaining = $width - mb_strlen($value);

        // A value that already fills the width (a long airport name clipped
        // to its limit) leaves no room for a gap: the label drops rather
        // than forcing the row a character past its fixed width.
        if ($remaining <= 0) {
            return $value;
        }

        $label = self::clip($label, max(0, $remaining - 1));
        $gap = $remaining - mb_strlen($label);

        return $label.str_repeat($char, $gap).$value;
    }

    private static function clip(string $text, int $width): string
    {
        return mb_strlen($text) > $width ? mb_substr($text, 0, $width) : $text;
    }

    private static function pad(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strlen($text)));
    }
}
