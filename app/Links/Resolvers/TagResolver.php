<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Links\LinkResolver;
use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * A cross-type tag page, /tags/{slug}.
 */
class TagResolver implements LinkResolver
{
    public function resolve(string $path): ?LinkPreviewData
    {
        if (preg_match('#^/tags/([a-z0-9-]+)$#', $path, $matches) !== 1) {
            return null;
        }

        $tag = Tag::query()->withCount('taggables')->where('slug', $matches[1])->first();

        if ($tag === null) {
            return null;
        }

        return LinkPreviewData::tag(
            $path,
            $tag->name,
            number_format($tag->taggables_count).' '.Str::plural('entry', $tag->taggables_count),
        );
    }
}
