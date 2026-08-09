<?php

namespace App\Console\Commands\Db;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Corrects food rows whose macros were imported in milligrams into columns that
 * hold grams, leaving values a thousand times too large.
 *
 * A command rather than a data migration because of when each runs. On a new
 * server, migrate builds an empty schema and any data migration would sweep
 * nothing; the rows only arrive afterwards, via db:copy. Fixing the source
 * database before the copy is the only ordering that works, and this is
 * re-runnable and reports what it would do first.
 */
#[Signature('calories:repair-units {--pretend : Show what would change, and write nothing}')]
#[Description('Correct food rows whose macros were stored in milligrams')]
class RepairCalorieUnits extends Command
{
    /**
     * How far above the portion's own weight a macro must be before it is
     * treated as a unit error.
     *
     * Food cannot contain more of anything than it weighs, so any excess is
     * wrong. The threshold is high because only the thousand-fold errors can be
     * corrected with confidence: rows overshooting by a factor of two or three
     * are wrong in some other way (a portion size, a per-100g figure) and
     * dividing those by a thousand would replace bad numbers with worse ones.
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
     * Rows whose macros exceed their own weight by more than {@see FACTOR}.
     *
     * Only gram-quantified rows can be judged this way. A row measured in
     * servings or pieces has no weight to compare against, so it is left alone
     * even where the numbers look wrong.
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
