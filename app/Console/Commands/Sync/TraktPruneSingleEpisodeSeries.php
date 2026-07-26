<?php

namespace App\Console\Commands\Sync;

use App\Actions\Trakt\RemovePlays;
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
     * Plays to remove from series with exactly one watched episode.
     *
     * The first twelve share a contiguous play-id block (8582881738 to
     * 8582913308) despite watch dates spanning 2018 to 2020, and cluster on
     * 20:15/21:15. Trakt ids increment on creation, so these were written in
     * one backdated batch - an imported viewing history, where anything that
     * auto-played got logged as a watch. Their timestamps are fabricated.
     *
     * `House` is not part of that batch (standalone id, ordinary 19:05
     * scrobble) but was confirmed as a mis-click too.
     *
     * `A Small Light` is deliberately absent: also a genuine scrobble, and
     * the most recent episode in the history, so it is likely still being
     * watched rather than abandoned.
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
     * Execute the console command.
     *
     * @return int Command exit code
     */
    public function handle(Trakt $trakt, RemovePlays $removePlays): int
    {
        $playIds = array_column(self::MANIFEST, 'play');

        $this->table(
            ['Episode', 'Play id', 'Local row'],
            array_map(fn (array $e): array => [
                $e['label'],
                $e['play'],
                Media::where('source', 'trakt')->where('source_id', (string) $e['play'])->exists() ? 'present' : 'MISSING',
            ], self::MANIFEST),
        );

        $this->newLine();
        $this->line(count($playIds).' plays to remove from Trakt, plus their now-empty series rows.');
        $this->line('A Small Light is not in this list and stays.');

        if (! $this->option('force')) {
            $this->newLine();
            $this->info('Dry run. Nothing was changed. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Removing plays from Trakt is permanent and cannot be undone.');

        if (! $this->confirm('Delete these plays from your Trakt history?', false)) {
            $this->info('Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $accessToken = $this->authoriseTrakt($trakt);
            $result = $removePlays($playIds, $accessToken, pruneEmptySeries: true);
        } catch (TraktException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Trakt removed {$result->deleted} play(s).");

        if (! $result->complete()) {
            $this->warn("Expected {$result->requested}, Trakt reported {$result->deleted}.");
        }

        if ($result->notFound !== []) {
            $this->warn('Trakt did not recognise: '.implode(', ', $result->notFound));
        }

        $this->info("Cleared {$result->clearedRows} local row(s) and {$result->clearedSeries} empty series.");

        return self::SUCCESS;
    }
}
