<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media Library's media model, stored in the `attachments` table so it does not
 * clash with the existing `media` timeline type (films/TV/books).
 */
class Attachment extends Media
{
    protected $table = 'attachments';

    /** Every subject tagged on this photograph, with their position. */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)
            ->using(PhotoTag::class)
            ->withPivot(['role', 'x', 'y'])
            ->withTimestamps();
    }
}
