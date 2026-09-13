<?php

namespace App\Models\Concerns;

use App\Enums\ResponseKind;
use App\Jobs\FetchCitationFor;
use App\Models\Citation;
use App\Support\Links;
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

            // A gesture has no words of its own: only a reply carries a quote,
            // whichever kind it arrived as and whatever wrote it.
            if ($model->response_kind !== ResponseKind::Reply) {
                $model->response_quote = null;
            }

            // A citation and a quote belong to the post they were taken from, so
            // pointing the reply elsewhere drops both, unless a quote is being
            // written in the same breath.
            if ($model->isDirty('response_url')) {
                $model->citation_id = null;

                if (! $model->isDirty('response_quote')) {
                    $model->response_quote = null;
                }
            }

            if (self::needsCitationFetch($model)) {
                $model->citation_id = Citation::query()->where('url', $model->response_url)->value('id');
            }
        });

        static::created(function (self $model): void {
            if (self::needsCitationFetch($model)) {
                FetchCitationFor::dispatch($model);
            }
        });

        // Only a new URL fetches: bulk resaves (timezone backfills) would otherwise retry every dead link.
        static::updated(function (self $model): void {
            if ($model->wasChanged('response_url') && self::needsCitationFetch($model)) {
                FetchCitationFor::dispatch($model);
            }
        });
    }

    /** Whether this replies to somebody else's post with no stored copy linked yet. */
    private static function needsCitationFetch(self $model): bool
    {
        return filled($model->response_url)
            && $model->citation_id === null
            && Links::internalPath($model->response_url) === null;
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
