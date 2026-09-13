<?php

namespace App\Models;

use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Polymorphic pivot binding a {@see Tag} to any taggable model. The table
 * carries no timestamps or surrogate key, only the morph triple.
 *
 * @property int $tag_id
 * @property string $taggable_type
 * @property int $taggable_id
 */
class Taggable extends Model
{
    public $timestamps = false;

    /** Pivot rows whose entry is published, so every tag count matches what a listing shows. */
    public function scopeListed(Builder $query): void
    {
        $query->whereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
            ->from('timeline_entries')
            ->whereColumn('timeline_entries.dataset', 'taggables.taggable_type')
            ->whereColumn('timeline_entries.entry_id', 'taggables.taggable_id')
            ->where('timeline_entries.status', EntryStatus::Published->value));
    }
}
