<?php

namespace App\Actions\Mentions;

use App\Models\Page;
use App\Models\TimelineEntry;
use App\Support\InteractionTarget;
use Illuminate\Database\Eloquent\Model;

/**
 * The model a site-relative path points at, or null when nothing here answers
 * to it.
 *
 * The link resolvers in app/Links match these same two shapes, but they return
 * a preview to render. A mention needs the row itself, so the lookups are
 * repeated here rather than the preview being reverse-engineered back into one.
 */
final class ResolveInternalTarget
{
    public function __invoke(string $path): ?Model
    {
        $model = $this->entry($path) ?? $this->page($path);

        // Visibility is the interaction allowlist's job, so a draft and a type
        // that takes no responses are refused by the same rule the webmention
        // endpoint uses. Recording a mention on either would put a response on
        // a page that cannot show one.
        return $model !== null && InteractionTarget::accepts($model) ? $model : null;
    }

    /** An entry permalink, /YYYY/MM/DD/slug. */
    private function entry(string $path): ?Model
    {
        if (preg_match('#^/(\d{4})/(\d{2})/(\d{2})/([a-z0-9-]+)$#', $path, $matches) !== 1) {
            return null;
        }

        return TimelineEntry::query()
            ->with('timelineable')
            ->whereDate('occurred_at', "{$matches[1]}-{$matches[2]}-{$matches[3]}")
            ->where('url_slug', $matches[4])
            ->first()?->timelineable;
    }

    /** A standalone page, /{slug}. */
    private function page(string $path): ?Page
    {
        if (preg_match('#^/([a-z][a-z0-9-]*)$#', $path, $matches) !== 1) {
            return null;
        }

        return Page::query()->where('slug', $matches[1])->first();
    }
}
