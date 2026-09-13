<?php

namespace App\Actions\Trakt;

use App\Data\TraktPruneResult;
use App\Models\Episode;
use App\Models\Film;
use App\Models\Series;
use App\Services\Trakt\Client;

/**
 * Remove plays from Trakt history and clear the local rows mirroring them. A local
 * row is deleted only once Trakt confirms: deleting early is unrecoverable, since
 * the next full sync re-imports the play as if it were a fresh watch.
 */
final class RemovePlays
{
    public function __construct(private readonly Client $trakt) {}

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
        $clearable = array_map('strval', array_merge($confirmedGone, array_map('intval', $localOnlyPlayIds)));

        // A history id could name either a film or an episode play; both
        // tables are checked, since the caller has no way to know which.
        $touchedSeries = Episode::query()
            ->where('source', 'trakt')
            ->whereIn('source_id', $clearable)
            ->pluck('series_id')
            ->filter()
            ->unique();

        $clearedRows = Film::query()->where('source', 'trakt')->whereIn('source_id', $clearable)->delete()
            + Episode::query()->where('source', 'trakt')->whereIn('source_id', $clearable)->delete();

        return new TraktPruneResult(
            requested: count($playIds),
            deleted: count($confirmedGone),
            notFound: $notFound,
            clearedRows: $clearedRows,
            clearedSeries: $pruneEmptySeries ? $this->pruneEmptySeries($touchedSeries->all()) : 0,
        );
    }

    /**
     * Delete series rows left with no episodes, scoped to those this run touched so
     * an unrelated empty series is not swept up.
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
