<?php

namespace App\Console\Commands\Sync;

use App\Actions\Trakt\RemoveEpisodePlays;
use App\Console\Commands\Concerns\AuthorisesTrakt;
use App\Exceptions\TraktException;
use App\Models\Media;
use App\Services\Trakt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trakt:prune-single-episode-series {--force : Actually delete; without this the command only reports}')]
#[Description('Remove one-off plays from series that were never really watched')]
class TraktPruneSingleEpisodeSeries extends Command
{
    use AuthorisesTrakt;

    /**
     * Plays to remove from series with exactly one watched episode. Hand-checked:
     * most come from one backdated import batch whose timestamps are fabricated,
     * and genuine scrobbles of shows still in progress are deliberately absent.
     *
     * @var list<array{label: string, play: int}>
     */
    private const MANIFEST = [
        ['label' => 'The Toys That Made Us S1E1', 'play' => 8582913308],
        ['label' => 'Altered Carbon S1E1', 'play' => 8582913071],
        ['label' => 'Money Heist S1E1', 'play' => 8582913302],
        ['label' => 'The Confession Tapes S1E1', 'play' => 8582913052],
        ['label' => 'Magic for Humans S1E1', 'play' => 8582906729],
        ['label' => 'Arrested Development S1E1', 'play' => 8582904946],
        ['label' => 'Big Mouth S1E1', 'play' => 8582902583],
        ['label' => 'The Umbrella Academy S1E1', 'play' => 8582902710],
        ['label' => 'Top Gear S7E7', 'play' => 8582901438],
        ['label' => 'Brain Games S5E1', 'play' => 8582893448],
        ['label' => 'The Trials of Gabriel Fernandez S1E1', 'play' => 8582887430],
        ['label' => "Inside the World's Toughest Prisons S4E1", 'play' => 8582881738],
        ['label' => 'House S1E1', 'play' => 11900054421],
    ];

    /**
     * The removal manifest, exposed so the read-only diagnostic command can
     * target the same plays without duplicating the list.
     *
     * @return list<array{label: string, play: int}>
     */
    public static function manifest(): array
    {
        return self::MANIFEST;
    }

    /**
     * Execute the console command.
     *
     * @return int Command exit code
     */
    public function handle(Trakt $trakt, RemoveEpisodePlays $removeEpisodePlays): int
    {
        $rows = Media::query()
            ->where('source', 'trakt')
            ->whereIn('source_id', array_map('strval', array_column(self::MANIFEST, 'play')))
            ->get()
            ->keyBy('source_id');

        // Removal targets episode trakt ids (meta.ids.trakt), not history ids:
        // the id-based endpoint reports these plays as not_found, whereas
        // removing the whole episode works and is what we want here anyway.
        $episodeIds = [];

        $tableRows = array_map(function (array $entry) use ($rows, &$episodeIds): array {
            $row = $rows->get((string) $entry['play']);
            $episodeTraktId = $row ? (int) data_get($row->meta, 'ids.trakt') : null;

            if ($episodeTraktId) {
                $episodeIds[] = $episodeTraktId;
            }

            return [$entry['label'], $episodeTraktId ?? 'MISSING', $row ? 'present' : 'MISSING'];
        }, self::MANIFEST);

        $this->table(['Episode', 'Episode trakt id', 'Local row'], $tableRows);

        $this->newLine();
        $this->line(count($episodeIds).' episodes to remove from Trakt, plus their now-empty series rows.');
        $this->line('A Small Light is not in this list and stays.');

        if (! $this->option('force')) {
            $this->newLine();
            $this->info('Dry run. Nothing was changed. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        if ($episodeIds === []) {
            $this->newLine();
            $this->info('Nothing to remove; the local rows are already gone.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Removing plays from Trakt is permanent and cannot be undone.');

        if (! $this->confirm('Delete these episodes from your Trakt history?', false)) {
            $this->info('Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $accessToken = $this->authoriseTrakt($trakt);
            $result = $removeEpisodePlays($episodeIds, $accessToken, pruneEmptySeries: true);
        } catch (TraktException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Trakt removed {$result->deleted} play(s).");

        if (! $result->complete()) {
            $this->warn("Expected {$result->requested} episode(s), Trakt removed plays for {$result->deleted}.");
        }

        if ($result->notFound !== []) {
            $this->warn('Trakt did not recognise episode ids: '.implode(', ', $result->notFound));
        }

        $this->info("Cleared {$result->clearedRows} local row(s) and {$result->clearedSeries} empty series.");

        return self::SUCCESS;
    }
}
