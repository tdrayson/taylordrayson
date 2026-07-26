<?php

namespace App\Actions\Trakt;

use App\Data\TraktPruneResult;
use App\Models\Media;
use App\Models\Series;
use App\Services\Trakt;

/**
 * Remove plays from Trakt history and clear the local rows that mirrored them.
 *
 * Trakt is the source of truth, so a local row is only deleted once Trakt has
 * confirmed the matching play is gone. Leaving a local row whose remote delete
 * failed is recoverable (re-run and it clears); deleting it early is not, as
 * the next full sync would quietly re-import the play and the mismatch would
 * look like a fresh watch.
 */
final class RemovePlays
{
    public function __construct(private readonly Trakt $trakt) {}

    /**
     * @param  array<int, int|string>  $playIds  Trakt history ids, NOT episode ids.
     * @param  array<int, int|string>  $localOnlyPlayIds  Plays already absent from Trakt, needing only local cleanup.
     */
    public function __invoke(
        array $playIds,
        string $accessToken,
        array $localOnlyPlayIds = [],
        bool $pruneEmptySeries = false,
    ): TraktPruneResult {
        $playIds = array_map('intval', $playIds);

        $this->trakt->removeHistory($playIds, $accessToken);

        // Confirm against authenticated history rather than the remove
        // endpoint's own counts: those have proven unreliable, and clearing
        // local rows on that basis has lost data. A play absent from the real
        // history is genuinely gone; one still present was not removed.
        $stillPresent = array_flip($this->trakt->authenticatedPlayIdsInHistory($accessToken));

        $confirmedGone = array_values(array_filter($playIds, fn (int $id): bool => ! isset($stillPresent[$id])));
        $notFound = array_values(array_filter($playIds, fn (int $id): bool => isset($stillPresent[$id])));

        // localOnly plays are known-absent from Trakt already (never sent to
        // the remove endpoint), so they clear without a history check.
        $clearable = array_merge($confirmedGone, array_map('intval', $localOnlyPlayIds));

        $touchedSeries = Media::query()
            ->where('source', 'trakt')
            ->whereIn('source_id', array_map('strval', $clearable))
            ->pluck('series_id')
            ->filter()
            ->unique();

        $clearedRows = Media::query()
            ->where('source', 'trakt')
            ->whereIn('source_id', array_map('strval', $clearable))
            ->delete();

        return new TraktPruneResult(
            requested: count($playIds),
            deleted: count($confirmedGone),
            notFound: $notFound,
            clearedRows: $clearedRows,
            clearedSeries: $pruneEmptySeries ? $this->pruneEmptySeries($touchedSeries->all()) : 0,
        );
    }

    /**
     * Delete series rows left with no episodes.
     *
     * Scoped to the series this run actually touched, so a series that was
     * already empty for some unrelated reason is left alone rather than being
     * swept up as a side effect of pruning something else.
     *
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
