<?php

namespace App\Models\Concerns;

use App\Enums\EntryStatus;
use App\Support\EntryInstant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The publishing state an entry or page carries, and who may list, open or search it.
 */
trait HasStatus
{
    public function initializeHasStatus(): void
    {
        $this->mergeCasts(['status' => EntryStatus::class, 'password' => 'hashed']);
        $this->makeHidden('password');
        $this->attributes['status'] ??= EntryStatus::Published->value;
    }

    /** Leaving draft fills an empty date; going back to draft keeps it, so the URL survives a republish. */
    public static function bootHasStatus(): void
    {
        static::saving(function (Model $model): void {
            if ($model->status !== EntryStatus::Draft && $model->hasCast('occurred_at') && $model->occurred_at === null) {
                $model->occurred_at = EntryInstant::nowLocal($model->timezone());
            }
        });
    }

    public function scopeListed(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), EntryStatus::Published->value);
    }

    /** Guests open anything but a draft; the owner opens everything. */
    public function scopeViewableBy(Builder $query, ?Authenticatable $user): void
    {
        if ($user === null) {
            $query->where($query->qualifyColumn('status'), '!=', EntryStatus::Draft->value);
        }
    }

    /** Guests find published entries; the owner finds everything but drafts. */
    public function scopeSearchable(Builder $query, ?Authenticatable $user): void
    {
        $user === null
            ? $query->where($query->qualifyColumn('status'), EntryStatus::Published->value)
            : $query->where($query->qualifyColumn('status'), '!=', EntryStatus::Draft->value);
    }

    public function isViewableBy(?Authenticatable $user): bool
    {
        return $user !== null || $this->status !== EntryStatus::Draft;
    }

    /** False only for a private entry the visitor has neither signed in for nor unlocked this session. */
    public function isUnlockedFor(Request $request): bool
    {
        return $this->status !== EntryStatus::Private
            || $request->user() !== null
            || ($request->hasSession() && $request->session()->get($this->unlockKey()) === true);
    }

    public function unlockKey(): string
    {
        return 'unlocked.'.$this->getMorphClass().'.'.$this->getKey();
    }
}
