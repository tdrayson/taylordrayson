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

    /**
     * Label left, value hard right. When the two cannot share the width,
     * the label takes its own line and the value wraps, indented, beneath it.
     */
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
     * A box sized to its content: $width is the floor every ordinary box
     * still renders at, not a clip point, so a line wider than it widens
     * the whole box instead of being cut mid-character.
     *
     * @param  list<string>  $lines
     */
    public static function box(array $lines, int $width = self::WIDTH): string
    {
        $inner = max($width - 4, $lines === [] ? 0 : max(array_map('mb_strlen', $lines)));
        $border = '+'.str_repeat('-', $inner + 2).'+';
        $out = [$border];

        foreach ($lines as $line) {
            $out[] = '| '.self::pad($line, $inner).' |';
        }

        $out[] = $border;

        return implode("\n", $out);
    }

    /** A proportional bar, for a sleep stage or a heart rate against its max. */
    public static function bar(float $fraction, int $width = 20): string
    {
        $filled = (int) round(max(0.0, min(1.0, $fraction)) * $width);

        return str_repeat('#', $filled).str_repeat('.', $width - $filled);
    }

    /**
     * A rating drawn as filled and empty stars against a five-star scale.
     * The empty character is a hyphen, not a dot, so an unfilled star does
     * not read as a fractional one.
     */
    public static function stars(float $fraction, int $max = 5): string
    {
        $filled = (int) round(max(0.0, min(1.0, $fraction)) * $max);

        return str_repeat('*', $filled).str_repeat('-', $max - $filled);
    }

    /**
     * A sheet's lines joined into its final text, each physical line
     * right-trimmed (splitting multi-line entries first, so a stacked row
     * or a whole box gets every line trimmed, not just its last).
     *
     * @param  list<string>  $lines
     */
    public static function join(array $lines): string
    {
        $physical = [];

        foreach ($lines as $line) {
            array_push($physical, ...explode("\n", $line));
        }

        return implode("\n", array_map('rtrim', $physical))."\n";
    }

    public static function heading(string $text, int $width = self::WIDTH): string
    {
        return mb_strtoupper(self::clip($text, $width))."\n".self::rule($width, '=');
    }

    /**
     * Wrap a body to the sheet's width, so an article or a note prints as a
     * column rather than one very long line.
     *
     * @param  bool  $cut  Whether a token wider than $width is cut mid-character.
     * @return list<string>
     */
    public static function wrap(string $text, int $width = self::WIDTH, bool $cut = true): array
    {
        return explode("\n", wordwrap($text, $width, "\n", $cut));
    }

    /**
     * Both ends of a row, filled to the width. Once they cannot share a
     * line, the label takes its own and the value wraps, indented, beneath it.
     */
    private static function fill(string $label, string $value, string $char, int $width): string
    {
        if (mb_strlen($label) + 1 + mb_strlen($value) > $width) {
            return self::stacked($label, $value, $width);
        }

        $gap = $width - mb_strlen($label) - mb_strlen($value);

        return $label.str_repeat($char, $gap).$value;
    }

    /** The label alone, then the value wrapped and indented two spaces beneath it. */
    private static function stacked(string $label, string $value, int $width): string
    {
        $indent = '  ';
        $lines = [self::clip($label, $width)];

        foreach (self::wrap($value, max(1, $width - mb_strlen($indent))) as $wrapped) {
            $lines[] = $indent.$wrapped;
        }

        return implode("\n", $lines);
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
