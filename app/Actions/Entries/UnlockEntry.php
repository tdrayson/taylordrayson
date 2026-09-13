<?php

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

final class UnlockEntry
{
    /**
     * A bcrypt hash of no real password. Checked whenever the model has none
     * of its own, so a guess against a non-private or passwordless model
     * costs the same as one against a real private entry's password, and
     * response timing cannot be used to find private entries by id.
     */
    private const DUMMY_HASH = '$2y$12$./6.4w1Pvj3dRu/mG6PO1uGy0NWkYcouD3dggGD9rIJFpFEQYU7BG';

    /** Remember the unlock for this session when the password matches a private entry. */
    public function __invoke(Model $model, string $password, Session $session): bool
    {
        $hash = $model->status === EntryStatus::Private ? $model->getRawOriginal('password') : null;
        $matches = Hash::check($password, $hash ?: self::DUMMY_HASH);

        if ($model->status !== EntryStatus::Private || ! $matches) {
            return false;
        }

        $session->put($model->unlockKey(), true);

        return true;
    }
}
