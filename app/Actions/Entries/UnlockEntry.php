<?php

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

final class UnlockEntry
{
    /** Remember the unlock for this session when the password matches a private entry. */
    public function __invoke(Model $model, string $password, Session $session): bool
    {
        if ($model->status !== EntryStatus::Private || ! Hash::check($password, (string) $model->getRawOriginal('password'))) {
            return false;
        }

        $session->put($model->unlockKey(), true);

        return true;
    }
}
