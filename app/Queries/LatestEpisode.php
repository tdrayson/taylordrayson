<?php

namespace App\Queries;

use App\Models\ThisWeekWith;

/**
 * The most recently published This Week With episode. Shared so the /now
 * widget and its export always name the same episode.
 */
final class LatestEpisode
{
    public function __invoke(): ?ThisWeekWith
    {
        return ThisWeekWith::query()->listed()->latest('occurred_at')->first();
    }
}
