<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Links\LinkResolver;
use App\Models\Page;

/**
 * A standalone content page, /{slug}. Registered last: the pattern is a
 * catch-all, the same reason routes/web.php keeps this route at the bottom.
 */
class PageResolver implements LinkResolver
{
    public function resolve(string $path): ?LinkPreviewData
    {
        if (preg_match('#^/([a-z][a-z0-9-]*)$#', $path, $matches) !== 1) {
            return null;
        }

        $page = Page::query()->where('slug', $matches[1])->where('published', true)->first();

        return $page === null ? null : LinkPreviewData::page($path, $page->title, $page->excerpt);
    }
}
