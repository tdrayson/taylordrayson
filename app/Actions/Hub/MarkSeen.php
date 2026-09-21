<?php

namespace App\Actions\Hub;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Stamps this visit and hands back the previous one.
 *
 * The stamp moves on the way in while the render still shows the marks the old
 * one produced, so a glance is honest and the next visit is clean.
 */
final class MarkSeen
{
    public function __invoke(User $user): ?Carbon
    {
        $previous = $user->hub_seen_at;

        $user->forceFill(['hub_seen_at' => now()])->save();

        return $previous;
    }
}
