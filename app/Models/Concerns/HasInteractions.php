<?php

namespace App\Models\Concerns;

use App\Models\Comment;
use App\Models\Reaction;
use App\Models\Webmention;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Anything that can be responded to: entries, and the pages the guestbook and
 * its siblings live on.
 */
trait HasInteractions
{
    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return MorphMany<Reaction, $this> */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /** @return MorphMany<Webmention, $this> */
    public function webmentions(): MorphMany
    {
        return $this->morphMany(Webmention::class, 'target');
    }
}
