<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Links\LinkResolver;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * A cross-type tag page, /tags/{slug}, counted by its listed entries.
 */
class TagResolver implements LinkResolver
{
    public function resolve(string $path): ?LinkPreviewData
    {
        if (preg_match('#^/tags/([a-z0-9-]+)$#', $path, $matches) !== 1) {
            return null;
        }

        $tag = Tag::query()
            ->withCount(['taggables' => fn (Builder $query) => $query->listed()])
            ->where('slug', $matches[1])
            ->first();

        // TagController 404s a tag with no listed entries, so there is no page to preview.
        if ($tag === null || $tag->taggables_count === 0) {
            return null;
        }

        return LinkPreviewData::tag(
            $path,
            $tag->name,
            number_format($tag->taggables_count).' '.Str::plural('entry', $tag->taggables_count),
        );
    }
}
