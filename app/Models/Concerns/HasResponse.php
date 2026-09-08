<?php

namespace App\Models\Concerns;

use App\Enums\ResponseKind;
use App\Jobs\FetchResponseTitle;
use App\Support\PostType;

/**
 * A post that can be a reply, like, repost or RSVP to a URL.
 *
 * Only the types written by hand carry this. Everything else records something
 * that happened rather than answering somebody, and a link in its description
 * is an ordinary mention.
 */
trait HasResponse
{
    /**
     * The three columns kept consistent with each other on the way in, at the
     * model rather than in a form request: the editor, the API and Micropub all
     * write these, and markup that claims a response with nothing to point at
     * is worse than no claim at all.
     */
    public static function bootHasResponse(): void
    {
        static::saving(function (self $model): void {
            if (blank($model->response_url)) {
                $model->response_kind = null;
            }

            if ($model->response_kind !== ResponseKind::Rsvp) {
                $model->rsvp_value = null;
            }

            // A title belongs to the URL it was read from, so pointing the post
            // somewhere else drops it rather than mislabelling the new target
            // until the fetch comes back. Unless a title is being written in the
            // same breath, which is somebody supplying one, not a stale one.
            if ($model->isDirty('response_url') && ! $model->isDirty('response_title')) {
                $model->response_title = null;
            }
        });

        static::saved(function (self $model): void {
            if ($model->wasChanged('response_url') && filled($model->response_url)) {
                FetchResponseTitle::dispatch($model);
            }
        });
    }

    /** What a parser will call this post, or null for a plain one. */
    public function responseKind(): ?ResponseKind
    {
        return PostType::of($this);
    }

    public function isResponse(): bool
    {
        return $this->responseKind() !== null;
    }
}
