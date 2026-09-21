<?php

namespace App\Actions\Hub;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reads and moves the hub's "last seen" stamp, kept as two steps so the write
 * can happen after the page is built: a query that throws while the page is
 * assembling must leave the stamp exactly where it was.
 */
final class MarkSeen
{
    /** The stamp from the previous visit, read without disturbing it. */
    public function previous(User $user): ?Carbon
    {
        return $user->hub_seen_at;
    }

    /** Moves the stamp to now, once the page it will be compared against is safely built. */
    public function __invoke(User $user): void
    {
        $user->forceFill(['hub_seen_at' => now()])->save();
    }
}
