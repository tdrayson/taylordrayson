<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry of mine linking to another, derived from the body rather than
 * declared: writing the link is the whole authoring step.
 *
 * The counterpart to Webmention, which records somebody else's site linking
 * here. This one never leaves the database, so it carries no author, no source
 * URL to re-fetch and no verification state.
 */
#[Fillable([
    'source_type',
    'source_id',
    'target_type',
    'target_id',
])]
class Mention extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
