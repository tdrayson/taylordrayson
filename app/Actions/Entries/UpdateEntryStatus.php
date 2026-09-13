<?php

namespace App\Actions\Entries;

use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Models\Food;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateEntryStatus
{
    /** Set an entry's status, and its password when one is given. */
    public function __invoke(Model $model, EntryStatus $status, ?string $password = null): Model
    {
        self::assertAllowed($model, $status, $password);

        $values = ['status' => $status, ...(filled($password) ? ['password' => $password] : [])];

        // A food day is one entry across many rows, so the whole day changes together.
        $rows = $model instanceof Food
            ? Food::query()->whereDate('occurred_at', $model->occurred_at->toDateString())->orderBy('id')->get()
            : collect([$model]);

        // A failure partway through the loop must not leave a food day on mixed statuses.
        DB::transaction(fn () => $rows->each(fn (Model $row) => $row->forceFill($values)->save()));

        return $model->refresh();
    }

    /**
     * @throws ValidationException When the type cannot be a draft, or private has no password to lock with.
     */
    public static function assertAllowed(Model $model, EntryStatus $status, ?string $password): void
    {
        if ($status === EntryStatus::Draft && ! self::canBeDraft($model)) {
            throw ValidationException::withMessages(['status' => 'Only hand-written entries can be a draft.']);
        }

        // A stored password only carries over while the entry stays private; one left from an earlier spell is not reused.
        $keepsPassword = $model->status === EntryStatus::Private && filled($model->password);

        if ($status === EntryStatus::Private && blank($password) && ! $keepsPassword) {
            throw ValidationException::withMessages(['status' => 'A private entry needs a password.']);
        }
    }

    public static function canBeDraft(Model $model): bool
    {
        return $model instanceof Page || (Datasets::forModel($model)?->draftable() ?? false);
    }
}
