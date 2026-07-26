<?php

namespace App\Console\Commands\Sync;

use App\Console\Commands\Concerns\AuthorisesTrakt;
use App\Exceptions\TraktException;
use App\Models\Media;
use App\Services\Trakt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trakt:diagnose-single-episode-removal')]
#[Description('Read-only: is each single-episode play actually still on Trakt (authenticated), vs the cached public feed?')]
class TraktDiagnoseSingleEpisodeRemoval extends Command
{
    use AuthorisesTrakt;

    /**
     * Compare the single-episode targets against three sources of truth and
     * change nothing.
     *
     * The public history feed is CDN-cached and can lag a removal; the
     * authenticated `/sync/history` read is the real account state. If a play
     * is absent from the authenticated history it is already gone from Trakt,
     * and the local row is a phantom left by a full sync re-importing the
     * stale feed.
     *
     * @return int Command exit code
     */
    public function handle(Trakt $trakt): int
    {
        $rows = Media::query()
            ->where('source', 'trakt')
            ->whereIn('source_id', array_map('strval', array_column(TraktPruneSingleEpisodeSeries::manifest(), 'play')))
            ->get();

        $targets = $rows
            ->mapWithKeys(fn (Media $row): array => [(int) data_get($row->meta, 'ids.trakt') => $row->meta['show_title'] ?? $row->title])
            ->filter(fn ($label, $id): bool => $id !== 0);

        try {
            $accessToken = $this->authoriseTrakt($trakt);

            $this->line('Reading authenticated history...');
            $authed = array_flip($trakt->authenticatedEpisodeTraktIdsInHistory($accessToken));

            $this->line('Reading public (cached) history...');
            $public = array_flip($trakt->episodeTraktIdsInHistory());
        } catch (TraktException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Episode', 'Episode trakt id', 'Authenticated (real)', 'Public (cached)'],
            $targets->map(fn (string $label, int $id): array => [
                $label,
                $id,
                isset($authed[$id]) ? 'present' : 'GONE',
                isset($public[$id]) ? 'present' : 'gone',
            ])->values()->all(),
        );

        $goneForReal = $targets->keys()->reject(fn (int $id): bool => isset($authed[$id]));

        $this->newLine();
        $this->info("{$goneForReal->count()} of {$targets->count()} target episodes are already GONE from your real Trakt history.");

        if ($goneForReal->isNotEmpty()) {
            $this->line('Those local rows are phantoms from a full sync re-importing the stale public feed; they can be cleared locally with no Trakt call.');
        }

        return self::SUCCESS;
    }
}
