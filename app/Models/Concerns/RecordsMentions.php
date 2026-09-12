<?php

namespace App\Models\Concerns;

use App\Actions\Mentions\SyncMentions;
use App\Models\Mention;
use App\Support\OutboundLinks;
use Illuminate\Database\Eloquent\Model;

/**
 * Records which of my own entries this one links to, and keeps that in step
 * with the links as they are edited.
 *
 * The twin of SendsWebmentions, deliberately: the same types carry both and the
 * same fields are read, so a link to somebody else's site and a link to one of
 * mine are noticed in the same breath. A check-in's note or a Strava
 * description pointing at a post of mine counts, the same as one typed into an
 * article.
 */
trait RecordsMentions
{
    /** Only a change here can add or remove a mention, publishing included. */
    private const MENTION_TRIGGERS = [...OutboundLinks::SOURCES, 'published'];

    public static function bootRecordsMentions(): void
    {
        // Nothing is queried for the overwhelming majority of saves, which are
        // sync commands writing rows that link nowhere.
        static::created(function (Model $model): void {
            if (OutboundLinks::internalPathsFor($model) !== []) {
                self::syncMentions($model);
            }
        });

        static::updated(function (Model $model): void {
            if ($model->wasChanged(self::MENTION_TRIGGERS)) {
                self::syncMentions($model);
            }
        });

        /**
         * The rows this entry wrote, cleared before it goes. The ones pointing
         * at it are cleared by HasInteractions, alongside the comments and
         * webmentions it was the target of.
         */
        static::deleting(function (Model $model): void {
            Mention::query()
                ->where('source_type', $model->getMorphClass())
                ->where('source_id', $model->getKey())
                ->delete();
        });
    }

    private static function syncMentions(Model $model): void
    {
        app(SyncMentions::class)($model);
    }
}
