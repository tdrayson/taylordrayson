<?php

namespace App\Presenters\Exports\Sheets;

use App\Data\Aspects\MealBreakdown;
use App\Data\Aspects\MealBreakdownMeal;
use App\Data\ExportData;

/**
 * A food day printed as its supermarket receipt: every item eaten, grouped
 * by meal, with calories where a shop receipt has prices. Reads the export
 * only: the meal rows come from the MealBreakdown aspect, already formatted
 * by the export, so this layout cannot drift from the data. `->raw` is read
 * once, for the receipt number, purely as the seed for the decorative
 * barcode's bar widths.
 */
final class FoodSheet
{
    private const WIDTH = 46;

    private const CONTENT = self::WIDTH - 1;

    /** The macro fields shown on the receipt, in the order their rows print. */
    private const MACROS = ['protein', 'carbs', 'fat'];

    public function render(ExportData $data): string
    {
        return Sheet::join([
            Sheet::rule(self::WIDTH, '='),
            Sheet::centre(mb_strtoupper($this->value($data, 'owner')), self::WIDTH),
            Sheet::centre(mb_strtoupper($this->value($data, 'receipt_date')), self::WIDTH),
            Sheet::rule(self::WIDTH, '='),
            '',
            ...$this->mealLines($data),
            Sheet::rule(self::WIDTH, '-'),
            ...$this->macroLines($data),
            Sheet::rule(self::WIDTH, '-'),
            ...$this->maybeFilled($data, 'TOTAL', 'calories'),
            '',
            ...$this->itemsLoggedLine($data),
            Sheet::rule(self::WIDTH, '-'),
            Sheet::centre('THANK YOU FOR EATING!', self::WIDTH),
            Sheet::centre(Sheet::barcode($this->seed($data)), self::WIDTH),
            Sheet::centre('No. '.$this->value($data, 'receipt_number'), self::WIDTH),
            Sheet::rule(self::WIDTH, '='),
        ]);
    }

    /**
     * Every meal's label and items, a blank line between meal groups rather
     * than after the last one.
     *
     * @return list<string>
     */
    private function mealLines(ExportData $data): array
    {
        $breakdown = $data->aspect(MealBreakdown::class);

        if (! $breakdown instanceof MealBreakdown) {
            return [];
        }

        $lines = [];

        foreach ($breakdown->meals as $index => $meal) {
            if ($index > 0) {
                $lines[] = '';
            }

            array_push($lines, ...$this->mealBlock($meal));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function mealBlock(MealBreakdownMeal $meal): array
    {
        $lines = [' '.mb_strtoupper($meal->label)];

        foreach ($meal->items as $item) {
            array_push($lines, ...$this->itemLines($item->name, $item->calories));
        }

        return $lines;
    }

    /**
     * One item's row. A name too long to share the line wraps across as many
     * lines as it needs, with the calories always hard right on the last of
     * them: on a receipt the price column never moves.
     *
     * @return list<string>
     */
    private function itemLines(string $name, string $calories): array
    {
        // Two spaces is the least gap that still reads as two columns, and
        // the same again is held back for the continuation indent.
        $nameWidth = self::CONTENT - mb_strwidth($calories) - 4;
        $wrapped = Sheet::wrap($name, $nameWidth);

        if ($wrapped === []) {
            return [' '.Sheet::row('', $calories, self::CONTENT)];
        }

        // Continuations are indented so a wrapped name reads as one item
        // rather than two.
        $first = array_shift($wrapped);
        $indented = array_map(fn (string $line): string => '  '.$line, $wrapped);
        $last = array_pop($indented) ?? $first;

        return [
            ...($indented === [] && $wrapped === [] ? [] : [' '.$first]),
            ...array_map(fn (string $line): string => ' '.$line, $indented),
            ' '.Sheet::row($last, $calories, self::CONTENT),
        ];
    }

    /**
     * The receipt's headline macros, each row labelled from the field's own
     * label rather than a literal, since PROTEIN/CARBOHYDRATE/FAT are the
     * field labels upper-cased.
     *
     * @return list<string>
     */
    private function macroLines(ExportData $data): array
    {
        $lines = [];

        foreach (self::MACROS as $key) {
            $field = $data->field($key);

            if ($field !== null) {
                $lines[] = ' '.Sheet::row(mb_strtoupper($field->label), $field->display, self::CONTENT);
            }
        }

        return $lines;
    }

    /**
     * A label/value row filled to the width, dropped entirely when the field
     * carries no value.
     *
     * @return list<string>
     */
    private function maybeFilled(ExportData $data, string $label, string $key): array
    {
        $field = $data->field($key);

        return $field === null ? [] : [' '.Sheet::row($label, $field->display, self::CONTENT)];
    }

    /**
     * @return list<string>
     */
    private function itemsLoggedLine(ExportData $data): array
    {
        $field = $data->field('items_logged');

        return $field === null ? [] : [' ITEMS LOGGED: '.$field->display];
    }

    /** The receipt number's raw id, the only `->raw` read: it seeds the barcode's bar widths, not its content. */
    private function seed(ExportData $data): int
    {
        $raw = $data->field('receipt_number')?->raw;

        return is_int($raw) ? $raw : 0;
    }

    /** A field's display string, or an empty one. Never a raw value. */
    private function value(ExportData $data, string $key): string
    {
        return $data->field($key)?->display ?? '';
    }
}
