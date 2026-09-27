<?php

namespace App\Observers;

use App\Jobs\ResolveLinkFavicons;
use App\Support\Links;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps an entry's external links marked without anyone remembering to run a
 * command. Attached to the types whose body is Portable Text.
 */
class LinkFaviconObserver
{
    public function saved(Model $model): void
    {
        $missing = array_values(array_filter(
            Links::hostsIn($model->content),
            fn (string $host): bool => Links::faviconUrl($host) === null,
        ));

        // No dispatch when every host is already stored, which is the usual
        // case: editing a published entry re-saves it on every keystroke-worth
        // of change, and re-queueing the same downloads each time is waste.
        if ($missing !== []) {
            ResolveLinkFavicons::dispatch($missing);
        }
    }
}
