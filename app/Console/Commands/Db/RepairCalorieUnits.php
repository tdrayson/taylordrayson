<?php

namespace App\Console\Commands\Db;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Corrects food rows whose macros were imported in milligrams into columns that
 * hold grams, leaving values a thousand times too large. A command, not a data
 * migration, because the rows arrive after migrate via db:copy.
 */
#[Signature('calories:repair-units {--pretend : Show what would change, and write nothing}')]
#[Description('Correct food rows whose macros were stored in milligrams')]
class RepairCalorieUnits extends Command
{
    /**
     * How far above the portion's own weight a macro must be before it counts as
     * a unit error. Set high: a row overshooting by two or three is wrong some
     * other way, and dividing it by a thousand would make it worse.
     */
    private const FACTOR = 100;

    /** Columns holding a mass in grams, all scaled by the same import. */
    private const GRAM_COLUMNS = ['fat', 'protein', 'carbs', 'saturated_fat', 'sugars', 'fibre'];

    /** Held in milligrams, and scaled by the same factor. */
    private const MILLIGRAM_COLUMNS = ['cholesterol', 'sodium'];

    public function handle(): int
    {
        $rows = $this->affectedRows();

        if ($rows->isEmpty()) {
            $this->components->info('No food rows have macros in milligrams.');

            return self::SUCCESS;
        }

        $this->components->info($rows->count().' row(s) to correct:');

        foreach ($rows as $row) {
            $this->components->twoColumnDetail(
                sprintf('#%d %s (%sg)', $row->id, $row->name, $row->quantity),
                sprintf(
                    '<fg=red>%sg carbs</> → <fg=green>%.1fg carbs, %.0fmg sodium</>',
                    $row->carbs,
                    $row->carbs / 1000,
                    ($row->sodium ?? 0) / 1000,
                ),
            );
        }

        if ($this->option('pretend')) {
            $this->components->info('Nothing written (--pretend).');

            return self::SUCCESS;
        }

        $updates = [];

        // 1000.0, not 1000: SQLite divides two integers as integers, and sodium
        // is whole, so `918031 / 1000` truncated to 918 instead of 918.03.
        //
        // Rounded to the two places the columns are declared with. SQLite does
        // not enforce that, so without it the source would keep digits MySQL
        // discards and the two databases would disagree after the copy.
        foreach ([...self::GRAM_COLUMNS, ...self::MILLIGRAM_COLUMNS] as $column) {
            $updates[$column] = DB::raw("ROUND({$column} / 1000.0, 2)");
        }

        DB::table('calories')->whereIn('id', $rows->pluck('id'))->update($updates);

        $this->components->info('Corrected '.$rows->count().' row(s).');

        return self::SUCCESS;
    }

    /**
     * Rows whose macros exceed their own weight by more than {@see FACTOR}. Only
     * gram-quantified rows qualify; servings and pieces have no weight to judge.
     *
     * @return Collection<int, object>
     */
    private function affectedRows(): Collection
    {
        return DB::table('calories')
            ->whereIn('units', ['Grams', 'Gram', 'g'])
            ->where('quantity', '>', 0)
            ->where(function ($query): void {
                foreach (['fat', 'protein', 'carbs'] as $column) {
                    $query->orWhereRaw("{$column} > quantity * ".self::FACTOR);
                }
            })
            ->orderBy('id')
            ->get();
    }
}
