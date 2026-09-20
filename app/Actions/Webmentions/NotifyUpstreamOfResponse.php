<?php

namespace App\Actions\Webmentions;

use App\Jobs\SendWebmentions;
use App\Support\InteractionTarget;
use Illuminate\Database\Eloquent\Model;

/**
 * Re-tells every site an entry links to that the entry has changed, because
 * somebody responded to it.
 *
 * This is the sending half of a salmention. The response is published inside my
 * h-entry as a nested h-cite, so a post upstream that I replied to can show it
 * as a further comment on its own page, but only if it is told to look again.
 *
 * Delayed rather than immediate: SendWebmentions is unique per entry, so the
 * wait is what turns a burst of replies into one round of notifications.
 */
final class NotifyUpstreamOfResponse
{
    /** @param  Model|null  $target  The entry responded to, absent when the row was orphaned. */
    public function __invoke(?Model $target): void
    {
        if ($target === null || ! InteractionTarget::sendsMentions($target)) {
            return;
        }

        SendWebmentions::dispatch($target)->delay(now()->addMinutes(SendWebmentions::RESPONSE_DELAY_MINUTES));
    }
}
