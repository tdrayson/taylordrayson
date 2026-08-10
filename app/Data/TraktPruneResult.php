<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Outcome of a Trakt history prune. `deleted` is confirmed by re-reading the
 * authenticated history, never taken from the remove endpoint's own count, which
 * reports false successes.
 */
final readonly class TraktPruneResult implements Arrayable, JsonSerializable
{
    /**
     * @param  int  $requested  Plays/episodes we asked Trakt to remove.
     * @param  int  $deleted  Confirmed gone from the authenticated history afterwards.
     * @param  list<int>  $notFound  Ids still present in history after the attempt.
     * @param  int  $clearedRows  Local media rows deleted.
     * @param  int  $clearedSeries  Local series rows deleted for having no episodes left.
     */
    public function __construct(
        public int $requested,
        public int $deleted,
        public array $notFound,
        public int $clearedRows,
        public int $clearedSeries,
    ) {}

    /**
     * Whether Trakt removed everything asked of it.
     */
    public function complete(): bool
    {
        return $this->deleted === $this->requested && $this->notFound === [];
    }

    /**
     * @return array{requested: int, deleted: int, not_found: list<int>, cleared_rows: int, cleared_series: int}
     */
    public function toArray(): array
    {
        return [
            'requested' => $this->requested,
            'deleted' => $this->deleted,
            'not_found' => $this->notFound,
            'cleared_rows' => $this->clearedRows,
            'cleared_series' => $this->clearedSeries,
        ];
    }

    /**
     * @return array{requested: int, deleted: int, not_found: list<int>, cleared_rows: int, cleared_series: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
