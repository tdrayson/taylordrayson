<?php

namespace App\Console\Commands\Sync;

use App\Models\Film;
use App\Models\TvEpisode;
use App\Services\Trakt\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * One-off: fill `overview` on films and episodes synced before the column existed.
 * Only blank rows are written, so it is safe to re-run.
 */
#[Signature('trakt:backfill-overviews {--apply : Write the overviews to the database}')]
#[Description('Fill the overview on existing Trakt films and episodes from watch history')]
class BackfillTraktOverviews extends Command
{
    /** Trakt history types mapped to the model and the payload key holding the overview. */
    private const TYPES = [
        'movies' => [Film::class, 'movie'],
        'episodes' => [TvEpisode::class, 'episode'],
    ];

    public function handle(Client $trakt): int
    {
        $apply = (bool) $this->option('apply');

        foreach (self::TYPES as $type => [$model, $key]) {
            $filled = 0;

            for ($page = 1; ($items = $trakt->historyPage($type, $page)) !== []; $page++) {
                foreach ($items as $item) {
                    $overview = trim((string) ($item[$key]['overview'] ?? ''));

                    if ($overview === '') {
                        continue;
                    }

                    $query = $this->blank($model::query(), (string) $item['id']);

                    $filled += $apply ? $query->update(['overview' => $overview]) : $query->count();
                }
            }

            $this->components->info(($apply ? 'Filled ' : 'Would fill ')."{$filled} {$type}.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Film|TvEpisode>  $query
     * @return Builder<Film|TvEpisode>
     */
    private function blank(Builder $query, string $sourceId): Builder
    {
        return $query->where('source', 'trakt')
            ->where('source_id', $sourceId)
            ->where(fn (Builder $blank) => $blank->whereNull('overview')->orWhere('overview', ''));
    }
}
