<?php

namespace App\Console\Commands\Sync;

use App\Models\Calorie;
use App\Services\Rovi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('rovi:sync-food {--days=3 : How many days back to re-check, catching food logged late for an earlier day}')]
#[Description('Sync the Rovi food diary into calories rows for a rolling window of recent days, keyed by Rovi id so re-runs stay idempotent')]
class RoviFoodSync extends Command
{
    private const SOURCE = 'rovi';

    /**
     * Re-sync the last --days of the Rovi food diary. Within that window each
     * diary item is upserted by its Rovi id (so a food logged today for an
     * earlier day appears, and edits update in place), and any Rovi-sourced row
     * that has since vanished from Rovi is removed. Only source=rovi rows are
     * ever touched, so the historical CSV-imported calories are left alone. The
     * per-day timeline entry is maintained by the CalorieTimelineObserver.
     *
     * A failed fetch returns early without reconciling, so an API outage can
     * never wipe the window.
     */
    public function handle(Rovi $rovi): int
    {
        $days = max(0, (int) $this->option('days'));
        $from = Carbon::today()->subDays($days)->toDateString();
        $to = Carbon::today()->toDateString();

        // A single, generous page: a few days of food is never near the limit.
        $envelope = $rovi->get('/v1/me/food', ['from' => $from, 'to' => $to, 'limit' => 500]);

        if ($envelope === null) {
            $this->warn('Rovi food fetch failed; skipping this run to avoid clobbering existing data.');

            return self::SUCCESS;
        }

        $items = array_values(array_filter($envelope['data'] ?? [], fn ($item): bool => isset($item['id'], $item['dateKey'])));
        $seenIds = [];
        $roviDates = [];

        foreach ($items as $item) {
            $seenIds[] = (string) $item['id'];
            $roviDates[] = (string) $item['dateKey'];

            Calorie::updateOrCreate(
                ['source' => self::SOURCE, 'source_id' => (string) $item['id']],
                $this->attributes($item),
            );
        }

        $removed = $this->reconcile($from, $to, array_values(array_unique($roviDates)), $seenIds);

        $this->info(sprintf('Synced %d Rovi food item(s) across %s..%s; removed %d superseded row(s).', count($seenIds), $from, $to, $removed));

        return self::SUCCESS;
    }

    /**
     * Map a Rovi diary item onto Calorie columns. The diary only carries the
     * macros below; saturated fat, sugars, cholesterol and sodium are not
     * provided and stay null.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attributes(array $item): array
    {
        return [
            'occurred_at' => Carbon::parse($item['dateKey'])->startOfDay(),
            'name' => $item['name'] ?? 'Food',
            'icon' => $item['emoji'] ?? null,
            'meal' => $this->meal($item['mealType'] ?? null),
            'quantity' => ($item['quantity'] ?? null) ?: ($item['servingSize'] ?? $item['baseQuantity'] ?? 1),
            'units' => ($item['servingUnit'] ?? null) ?: ($item['baseUnit'] ?? 'serving'),
            'calories' => (int) round((float) ($item['calories'] ?? 0)),
            'fat' => $item['fats'] ?? null,
            'protein' => $item['protein'] ?? null,
            'carbs' => $item['carbs'] ?? null,
            'fibre' => $item['fibre'] ?? null,
        ];
    }

    /**
     * Normalise Rovi's "Breakfast"/"Lunch"/"Dinner"/"Snack" to the lowercase
     * values the app stores, where snacks is plural.
     */
    private function meal(?string $mealType): string
    {
        $meal = strtolower(trim((string) $mealType));

        return match ($meal) {
            'snack' => 'snacks',
            '' => 'snacks',
            default => $meal,
        };
    }

    /**
     * Reconcile the synced window. Within [from, to] a row is removed when it is
     * either (A) a Rovi row no longer in the diary (deleted or re-logged), or
     * (B) a non-Rovi row on a date Rovi now owns, so the boundary day where the
     * historical CSV import meets the live diary is not double counted. Dates
     * Rovi did not log keep their historical rows. Rows are deleted as instances
     * so the timeline observer re-anchors or removes each day's entry.
     *
     * @param  list<string>  $roviDates  The distinct dateKeys Rovi returned.
     * @param  list<string>  $seenIds
     */
    private function reconcile(string $from, string $to, array $roviDates, array $seenIds): int
    {
        $stale = Calorie::query()
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->where(function ($query) use ($roviDates, $seenIds): void {
                // (A) Rovi rows that have since vanished from the diary.
                $query->where(fn ($rovi) => $rovi
                    ->where('source', self::SOURCE)
                    ->when($seenIds !== [], fn ($q) => $q->whereNotIn('source_id', $seenIds)));

                // (B) Historical (non-Rovi) rows on a date Rovi now owns.
                if ($roviDates !== []) {
                    $query->orWhere(fn ($csv) => $csv
                        ->where(fn ($source) => $source->where('source', '!=', self::SOURCE)->orWhereNull('source'))
                        ->where(function ($dates) use ($roviDates): void {
                            foreach ($roviDates as $index => $date) {
                                $dates->{$index === 0 ? 'whereDate' : 'orWhereDate'}('occurred_at', $date);
                            }
                        }));
                }
            })
            ->get();

        $stale->each->delete();

        return $stale->count();
    }
}
