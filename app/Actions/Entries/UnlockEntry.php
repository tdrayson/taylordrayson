<?php

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

final class UnlockEntry
{
    /**
     * A bcrypt hash of no real password. Checked whenever there is no private
     * model's own to check, so a guess against a missing, non-private or
     * passwordless model costs the same as one against a real private entry's
     * password, and response timing cannot be used to find entries by id.
     */
    private const DUMMY_HASH = '$2y$12$./6.4w1Pvj3dRu/mG6PO1uGy0NWkYcouD3dggGD9rIJFpFEQYU7BG';

    /** Remember the unlock for this session when the password matches a private entry. */
    public function __invoke(?Model $model, string $password, Session $session): bool
    {
        $isPrivate = $model?->status === EntryStatus::Private;
        $hash = $isPrivate ? $model->getRawOriginal('password') : null;
        $matches = Hash::check($password, $hash ?: self::DUMMY_HASH);

        if (! $isPrivate || ! $matches) {
            return false;
        }

        $session->put($model->unlockKey(), true);

        return true;
    }
}
