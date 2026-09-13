<?php

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;

final class UnlockEntry
{
    /** Remember the unlock for this session when the password matches a private entry. */
    public function __invoke(?Model $model, string $password, Session $session): bool
    {
        $stored = $model?->status === EntryStatus::Private ? $model->password : null;

        if ($stored === null || ! hash_equals($stored, $password)) {
            return false;
        }

        $session->put($model->unlockKey(), true);

        return true;
    }
}
