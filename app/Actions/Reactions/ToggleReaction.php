<?php

namespace App\Actions\Reactions;

use App\Enums\ReactionType;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds or removes one visitor's reaction. A second click on the same emoji
 * takes it back, which is the only way to undo one when nobody is signed in.
 */
final class ToggleReaction
{
    /** Whether the reaction is now on. */
    public function __invoke(Model $target, ReactionType $type, string $identity): bool
    {
        $existing = Reaction::query()
            ->where('reactable_type', $target->getMorphClass())
            ->where('reactable_id', $target->getKey())
            ->where('type', $type)
            ->where('identity_key', $identity)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        try {
            $target->morphMany(Reaction::class, 'reactable')
                ->create(['type' => $type, 'identity_key' => $identity]);
        } catch (UniqueConstraintViolationException) {
            // Two clicks landed together. The row exists either way, which is
            // the state the caller asked for.
        }

        return true;
    }
}
