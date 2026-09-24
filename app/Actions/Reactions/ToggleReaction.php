<?php

namespace App\Actions\Reactions;

use App\Enums\ReactionType;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Sets one visitor's reaction to an entry. A person holds one at a time: a
 * different emoji moves the one they have, and the same emoji again takes it
 * back, which is the only way to undo one when nobody is signed in.
 */
final class ToggleReaction
{
    /** Whether the visitor is now reacting to this. */
    public function __invoke(Model $target, ReactionType $type, string $identity): bool
    {
        $existing = Reaction::query()
            ->where('reactable_type', $target->getMorphClass())
            ->where('reactable_id', $target->getKey())
            ->where('identity_key', $identity)
            ->first();

        if ($existing?->type === $type) {
            $existing->delete();

            return false;
        }

        // Moved rather than replaced, so the row keeps the time they first
        // reacted instead of looking like a new reaction every time they
        // change their mind.
        if ($existing !== null) {
            $existing->update(['type' => $type]);

            return true;
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
