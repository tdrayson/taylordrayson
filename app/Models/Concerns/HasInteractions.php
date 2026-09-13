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
    /**
     * Take the responses with the thing they were left on.
     *
     * A morph row names a type and an id, and nothing in the database stops it
     * naming one that no longer exists. Left behind, the rows are unreachable
     * until an autoincrement hands the same id to a new entry, at which point
     * somebody else's conversation appears under it.
     *
     * Outgoing sends are deliberately not swept up: they record what we told
     * other sites, which stays true after the post is gone.
     */
    public static function bootHasInteractions(): void
    {
        static::deleting(function (self $model): void {
            $model->comments()->delete();
            $model->reactions()->delete();
            $model->webmentions()->delete();
        });
    }

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
