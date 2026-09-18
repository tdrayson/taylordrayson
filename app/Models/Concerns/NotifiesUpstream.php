<?php

namespace App\Models\Concerns;

use App\Actions\Webmentions\NotifyUpstreamOfResponse;
use App\Enums\CommentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * A response that, once it is visible, makes the entry it sits under worth
 * re-announcing to everything that entry links to.
 *
 * Only written responses count. A like, repost, bookmark or reaction changes no
 * words on the page, and firing a round of webmentions per click would be a
 * denial of service with my own name on it.
 *
 * Visibility is the trigger, not arrival: nothing is published until it is
 * approved, so a held response has nothing for an upstream parser to find, and
 * approving one from the moderation queue is what sends.
 */
trait NotifiesUpstream
{
    public static function bootNotifiesUpstream(): void
    {
        static::created(function (Model $model): void {
            self::notifyUpstream($model);
        });

        // Only a status change, or approving a comment and then editing an
        // unrelated column on it would re-announce the entry every time.
        static::updated(function (Model $model): void {
            if ($model->wasChanged('status')) {
                self::notifyUpstream($model);
            }
        });
    }

    /** The entry this response was left on, which is the thing that gets re-sent. */
    abstract public function upstreamSubject(): ?Model;

    /** Whether this response is one an upstream author would see quoted in my entry. */
    abstract public function isWrittenResponse(): bool;

    private static function notifyUpstream(Model $model): void
    {
        if ($model->status !== CommentStatus::Approved || ! $model->isWrittenResponse()) {
            return;
        }

        app(NotifyUpstreamOfResponse::class)($model->upstreamSubject());
    }
}
