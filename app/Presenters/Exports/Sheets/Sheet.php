<?php

namespace App\Presenters\Exports\Sheets;

/**
 * Drawing primitives shared by every typed sheet: rules, centred text, aligned
 * rows, dotted leaders, boxes and bars. Measured in display columns rather
 * than bytes or code points, because an accented place name or an emoji
 * would otherwise skew its own row.
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
        $left = intdiv($width - mb_strwidth($text), 2);

        return str_repeat(' ', $left).$text.str_repeat(' ', $width - $left - mb_strwidth($text));
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
        $inner = max($width - 4, $lines === [] ? 0 : max(array_map('mb_strwidth', $lines)));
        $border = '+'.str_repeat('-', $inner + 2).'+';
        $out = [$border];

        foreach ($lines as $line) {
            $out[] = '| '.self::pad($line, $inner).' |';
        }

        $out[] = $border;

        return implode("\n", $out);
    }

    /**
     * A perforated ticket stub: alternating '(' / ')' tear edges down the
     * sides, '.'/"'" corners, and a ')====((' rule between sections. Auto-
     * widens to its longest line, the same way box() does. A null entry
     * draws a section rule instead of a content line.
     *
     * @param  list<string|null>  $lines
     */
    public static function ticket(array $lines, int $width = self::WIDTH): string
    {
        $ordered = array_values($lines);
        $inner = $width - 4;

        // A line's own width only tells us its *shape's* content budget: an
        // odd-shape line carries two more chrome characters than an even one,
        // so its width converts back to inner two less than an even line's would.
        foreach ($ordered as $index => $line) {
            if ($line !== null) {
                $inner = max($inner, mb_strwidth($line) - (($index + 1) % 2 === 1 ? 2 : 0));
            }
        }

        $out = ['.'.str_repeat('-', $inner + 2).'.'];

        foreach ($ordered as $index => $line) {
            // Every line takes the shape its position demands, rules included,
            // so the tear edge zigzags unbroken down the side.
            $odd = ($index + 1) % 2 === 1;
            $budget = $odd ? $inner + 2 : $inner;

            $body = $line === null
                ? str_repeat('=', $budget)
                : self::pad($line, $budget);

            $out[] = $odd ? '('.$body.')' : ' )'.$body.'((';
        }

        $out[] = "'".str_repeat('-', $inner + 2)."'";

        return implode("\n", $out);
    }

    /** A proportional bar, for a sleep stage or a heart rate against its max. */
    public static function bar(float $fraction, int $width = 20): string
    {
        $filled = (int) round(max(0.0, min(1.0, $fraction)) * $width);

        return str_repeat('#', $filled).str_repeat('.', $width - $filled);
    }

    /**
     * A decorative barcode, deterministic from a seed (an entry id) so the
     * same entry always renders the same bars rather than a fresh random one
     * per request.
     */
    public static function barcode(int $seed, int $groups = 6, int $max = 5): string
    {
        $bars = [];

        for ($i = 0; $i < $groups; $i++) {
            $bars[] = str_repeat('|', 1 + abs(($seed + $i * 97) % $max));
        }

        return '['.implode(' ', $bars).']';
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
     * column rather than one very long line. Existing newlines are kept as
     * hard breaks; each paragraph between them is wrapped independently.
     *
     * `wordwrap()` is not used here: it measures bytes, so a line carrying an
     * accented character or an emoji would wrap in the wrong place. Width is
     * measured in display columns throughout instead.
     *
     * @param  bool  $cut  Whether a token wider than $width is cut mid-character.
     * @return list<string>
     */
    public static function wrap(string $text, int $width = self::WIDTH, bool $cut = true): array
    {
        $lines = [];

        foreach (explode("\n", $text) as $paragraph) {
            array_push($lines, ...self::wrapParagraph($paragraph, $width, $cut));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private static function wrapParagraph(string $paragraph, int $width, bool $cut): array
    {
        if ($paragraph === '') {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach (array_filter(explode(' ', $paragraph), fn (string $word): bool => $word !== '') as $word) {
            foreach (self::pieces($word, $width, $cut) as $piece) {
                $candidate = $current === '' ? $piece : $current.' '.$piece;

                if (mb_strwidth($candidate) <= $width) {
                    $current = $candidate;

                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                }

                $current = $piece;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * A word split into width-sized chunks when it is wider than the line
     * and cutting is allowed; otherwise the word is returned whole, so it
     * overflows its line rather than being cut mid-character.
     *
     * @return list<string>
     */
    private static function pieces(string $word, int $width, bool $cut): array
    {
        if (! $cut || mb_strwidth($word) <= $width) {
            return [$word];
        }

        $pieces = [];

        while ($word !== '') {
            $chunk = mb_strimwidth($word, 0, $width, '');

            // A single double-width character wider than $width has no chunk that fits; take it anyway.
            if ($chunk === '') {
                $chunk = mb_substr($word, 0, 1);
            }

            $pieces[] = $chunk;
            $word = mb_substr($word, mb_strlen($chunk));
        }

        return $pieces;
    }

    /**
     * Both ends of a row, filled to the width. Once they cannot share a
     * line, the label takes its own and the value wraps, indented, beneath it.
     */
    private static function fill(string $label, string $value, string $char, int $width): string
    {
        if (mb_strwidth($label) + 1 + mb_strwidth($value) > $width) {
            return self::stacked($label, $value, $width);
        }

        $gap = $width - mb_strwidth($label) - mb_strwidth($value);

        return $label.str_repeat($char, $gap).$value;
    }

    /** The label alone, then the value wrapped and indented two spaces beneath it. */
    private static function stacked(string $label, string $value, int $width): string
    {
        $indent = '  ';
        $lines = [self::clip($label, $width)];

        foreach (self::wrap($value, max(1, $width - mb_strwidth($indent))) as $wrapped) {
            $lines[] = $indent.$wrapped;
        }

        return implode("\n", $lines);
    }

    private static function clip(string $text, int $width): string
    {
        return mb_strwidth($text) > $width ? mb_strimwidth($text, 0, $width, '') : $text;
    }

    private static function pad(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strwidth($text)));
    }
}
