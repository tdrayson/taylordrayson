<?php

namespace App\Actions\Trakt;

use App\Data\TraktPruneResult;
use App\Models\Media;
use App\Models\Series;
use App\Services\Trakt;
use Illuminate\Support\Collection;

/**
 * Remove whole episodes from Trakt history (by episode trakt id) and clear the
 * local rows that mirrored them.
 *
 * The episode-level sibling of {@see RemovePlays}: use this when an episode
 * should not appear at all, rather than when one specific play among several
 * must go. As with RemovePlays, a local row is only deleted once Trakt has
 * confirmed the episode is gone, so a failed remote removal leaves a
 * recoverable mismatch rather than a silently resurrecting one.
 */
final class RemoveEpisodePlays
{
    public function __construct(private readonly Trakt $trakt) {}

    /**
     * @param  array<int, int>  $episodeTraktIds  Episode trakt ids (meta.ids.trakt), NOT history ids.
     */
    public function __invoke(array $episodeTraktIds, string $accessToken, bool $pruneEmptySeries = false): TraktPruneResult
    {
        $episodeTraktIds = array_map('intval', $episodeTraktIds);

        $this->trakt->removeEpisodePlays($episodeTraktIds, $accessToken);

        // Confirm against authenticated history rather than the remove
        // endpoint's own counts: those have proven unreliable (a false
        // deleted count, and a zero-deleted response for plays already gone),
        // and clearing local rows on that basis has twice lost data. An
        // episode absent from the real history is genuinely gone; one still
        // present was not removed and its local row must stay.
        $stillPresent = array_flip($this->trakt->authenticatedEpisodeTraktIdsInHistory($accessToken));

        $confirmedGone = array_values(array_filter($episodeTraktIds, fn (int $id): bool => ! isset($stillPresent[$id])));
        $notFound = array_values(array_filter($episodeTraktIds, fn (int $id): bool => isset($stillPresent[$id])));

        $rows = $this->localRowsFor($confirmedGone);
        $touchedSeries = $rows->pluck('series_id')->filter()->unique();

        $clearedRows = 0;

        foreach ($rows as $row) {
            $row->delete();
            $clearedRows++;
        }

        return new TraktPruneResult(
            requested: count($episodeTraktIds),
            deleted: count($confirmedGone),
            notFound: $notFound,
            clearedRows: $clearedRows,
            clearedSeries: $pruneEmptySeries ? $this->pruneEmptySeries($touchedSeries->all()) : 0,
        );
    }

    /**
     * Local episode rows whose meta trakt id is in the confirmed-gone set.
     *
     * Matched in PHP rather than with a JSON `where`, since production is not
     * SQLite and the codebase avoids JSON-path predicates for portability.
     * The candidate set is bounded to Trakt episodes, so the scan is cheap.
     *
     * @param  array<int, int>  $episodeTraktIds
     * @return Collection<int, Media>
     */
    private function localRowsFor(array $episodeTraktIds): Collection
    {
        if ($episodeTraktIds === []) {
            return collect();
        }

        $wanted = array_flip($episodeTraktIds);

        return Media::query()
            ->where('source', 'trakt')
            ->where('type', 'episode')
            ->get()
            ->filter(fn (Media $row): bool => isset($wanted[(int) data_get($row->meta, 'ids.trakt')]));
    }

    /**
     * @param  array<int, int>  $seriesIds
     */
    private function pruneEmptySeries(array $seriesIds): int
    {
        return Series::query()
            ->whereIn('id', $seriesIds)
            ->whereDoesntHave('episodes')
            ->delete();
    }
}
