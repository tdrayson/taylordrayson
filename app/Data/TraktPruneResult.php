<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Outcome of a Trakt history prune.
 *
 * `requested` and `deleted` are reported separately because Trakt answers 200
 * even when it matched nothing, so a caller that trusts the status alone
 * cannot tell a full success from a silent partial one.
 */
final readonly class TraktPruneResult implements Arrayable, JsonSerializable
{
    /**
     * @param  int  $requested  Plays we asked Trakt to remove.
     * @param  int  $deleted  Plays Trakt confirmed it removed.
     * @param  list<int>  $notFound  Play ids Trakt did not recognise.
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
