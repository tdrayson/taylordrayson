<?php

namespace App\Console\Commands;

use App\Models\Checkin;
use App\Models\Event;
use App\Services\GoogleMaps\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

/**
 * One-off: fill the street and postcode that events and checkins could not
 * store before those columns existed.
 *
 * Reverse-geocodes the coordinates already on the row, so it costs one Maps
 * call per entry. Resumable by design: it only ever selects rows still missing
 * a part, so a run that stops partway can simply be run again.
 */
#[Signature('entries:backfill-addresses {--apply : Write the resolved parts to the database} {--limit=0 : Stop after this many rows, 0 for all}')]
#[Description('Fill the street and postcode on entries that have coordinates but no address parts')]
class BackfillAddressParts extends Command
{
    /** Google allows 50 requests a second; this stays far under it. */
    private const PAUSE_MILLISECONDS = 120;

    public function handle(Client $maps): int
    {
        $limit = (int) $this->option('limit');
        $apply = (bool) $this->option('apply');

        $rows = $this->pending($limit);

        if ($rows->isEmpty()) {
            $this->components->info('Nothing is missing an address part.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->components->warn("{$rows->count()} rows would be filled. Re-run with --apply to write them.");
        }

        $filled = 0;
        $missed = 0;

        $this->withProgressBar($rows, function (Model $row) use ($maps, $apply, &$filled, &$missed): void {
            $place = $maps->reverse((float) $row->latitude, (float) $row->longitude);

            // A coordinate in open water or a country without postcodes resolves
            // to nothing useful; leaving the row alone is the honest outcome.
            $parts = array_filter([
                'address' => $place['street'] ?? null,
                'postcode' => $place['postcode'] ?? null,
            ], fn (?string $value): bool => $value !== null && $value !== '');

            if ($parts === []) {
                $missed++;

                return;
            }

            if ($apply) {
                // Only the blanks: a value already on the row was entered or
                // synced deliberately and outranks a guess from a coordinate.
                $row->fill(array_diff_key($parts, array_filter($row->only(array_keys($parts)))))->save();
            }

            $filled++;

            Sleep::for(self::PAUSE_MILLISECONDS)->milliseconds();
        });

        $this->newLine(2);
        $this->components->info(($apply ? 'Filled ' : 'Would fill ')."{$filled} rows, {$missed} had nothing to resolve.");

        return self::SUCCESS;
    }

    /**
     * Events and checkins with a coordinate but a gap in their address, newest
     * first so a capped run does the entries most likely to be looked at.
     *
     * @return Collection<int, Model>
     */
    private function pending(int $limit)
    {
        $events = $this->located(Event::query())
            ->where(fn (Builder $query) => $query->whereNull('address')->orWhereNull('postcode'));

        $checkins = $this->located(Checkin::query())->whereNull('postcode');

        $rows = $events->get()->concat($checkins->get())
            ->sortByDesc(fn (Model $row): string => (string) $row->occurred_at)
            ->values();

        return $limit > 0 ? $rows->take($limit) : $rows;
    }

    /** @param  Builder<Model>  $query */
    private function located(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }
}
