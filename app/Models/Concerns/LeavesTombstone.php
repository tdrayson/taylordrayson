<?php

namespace App\Models\Concerns;

use App\Enums\EntryStatus;
use App\Models\Tombstone;
use Illuminate\Database\Eloquent\Model;

/**
 * Leaves a tombstone at a public post's address when it is deleted, so the URL
 * answers 410 Gone. A live post later published at the same address wins, so
 * a tombstone never needs clearing.
 */
trait LeavesTombstone
{
    public static function bootLeavesTombstone(): void
    {
        // url() reads the timeline row's slug, and that row is deleted alongside the model.
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'timelineEntry')) {
                $model->loadMissing('timelineEntry');
            }
        });

        static::deleted(function (Model $model): void {
            if (! self::leftPublicly($model)) {
                return;
            }

            Tombstone::query()->updateOrCreate(['path' => $model->url()], ['deleted_at' => now()]);
        });
    }

    /** Whether anyone else could have seen or linked to it: drafts and password-locked posts were never public. */
    public static function leftPublicly(Model $model): bool
    {
        return in_array($model->status ?? null, [EntryStatus::Published, EntryStatus::Unlisted], strict: true);
    }
}
