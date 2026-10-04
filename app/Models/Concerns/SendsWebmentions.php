<?php

namespace App\Models\Concerns;

use App\Jobs\SendWebmention;
use App\Jobs\SendWebmentions;
use App\Models\WebmentionSend;
use App\Support\InteractionTarget;
use App\Support\OutboundLinks;
use Illuminate\Database\Eloquent\Model;

/**
 * Dispatches outgoing webmentions when a post that carries links is saved or deleted.
 *
 * Hooked on the model rather than a controller because entries arrive from the
 * authoring form, Micropub, the API and the sync commands, and only the model
 * sees all four.
 */
trait SendsWebmentions
{
    /** Stands in for the content fingerprint on a retraction, so a post later published at the same URL reads as changed. */
    private const DELETED_HASH = 'deleted';

    public static function bootSendsWebmentions(): void
    {
        // Two events rather than one `saved`, because `wasRecentlyCreated`
        // stays true for the rest of the instance's life: a later save on the
        // same object would read as a fresh publish and re-notify everyone.
        static::created(function (Model $model): void {
            // A new post with no links has told nobody anything, so there is
            // nothing to send and nothing to retract either.
            if (OutboundLinks::for($model) !== []) {
                self::queueWebmentions($model);
            }
        });

        static::deleted(function (Model $model): void {
            if (config('webmentions.send') && InteractionTarget::sendsMentions($model)) {
                self::queueRetraction($model);
            }
        });

        static::updated(function (Model $model): void {
            // Only a link changing, or links held back while unpublished going
            // out, can need a send. This is also what keeps the sync commands quiet.
            if ($model->wasChanged(OutboundLinks::SOURCES) || self::startedSending($model)) {
                self::queueWebmentions($model);
            }
        });
    }

    /** Whether this save moved the model from a status that sends nothing into one that sends. */
    private static function startedSending(Model $model): bool
    {
        if (! $model->wasChanged('status')) {
            return false;
        }

        $before = (clone $model)->setRawAttributes($model->getRawOriginal());

        return ! InteractionTarget::sendsMentions($before) && InteractionTarget::sendsMentions($model);
    }

    /**
     * One last send to every site that took a mention from the post, so each
     * re-fetches it, gets the 410 and drops its copy.
     */
    private static function queueRetraction(Model $model): void
    {
        $sourceUrl = rtrim((string) config('app.url'), '/').$model->url();

        $targets = WebmentionSend::query()
            ->where('source_url', $sourceUrl)
            ->whereIn('status', ['sent', 'failed'])
            ->pluck('target_url');

        foreach ($targets as $target) {
            SendWebmention::dispatch($sourceUrl, $target, self::DELETED_HASH)->afterCommit();
        }
    }

    private static function queueWebmentions(Model $model): void
    {
        if (InteractionTarget::sendsMentions($model)) {
            // Telling another site about a post is not undoable, so it waits for
            // the transaction the sync commands and imports write inside. The
            // queue connections all set after_commit false.
            SendWebmentions::dispatch($model)->afterCommit();
        }
    }
}
