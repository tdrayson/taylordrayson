<?php

namespace App\Models\Concerns;

use App\Jobs\SendWebmentions;
use App\Support\InteractionTarget;
use App\Support\OutboundLinks;
use Illuminate\Database\Eloquent\Model;

/**
 * Dispatches outgoing webmentions when a post that carries links is saved.
 *
 * Hooked on the model rather than a controller because entries arrive from the
 * authoring form, Micropub, the API and the sync commands, and only the model
 * sees all four.
 */
trait SendsWebmentions
{
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

        static::updated(function (Model $model): void {
            // Nothing else can add or remove a link, so nothing else can need a
            // send. This is also what keeps the sync commands quiet.
            if ($model->wasChanged(OutboundLinks::SOURCES)) {
                self::queueWebmentions($model);
            }
        });
    }

    private static function queueWebmentions(Model $model): void
    {
        if (InteractionTarget::accepts($model)) {
            SendWebmentions::dispatch($model);
        }
    }
}
