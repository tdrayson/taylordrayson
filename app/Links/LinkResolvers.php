<?php

namespace App\Links;

use App\Data\LinkPreviewData;
use App\Links\Resolvers\ArchiveResolver;
use App\Links\Resolvers\EntryResolver;
use App\Links\Resolvers\LiveResolver;
use App\Links\Resolvers\PageResolver;
use App\Links\Resolvers\PeriodResolver;
use App\Links\Resolvers\StoryResolver;
use App\Links\Resolvers\TagResolver;

/**
 * The resolvers, in the order they get a look at a path. First non-null wins.
 *
 * Order is load-bearing at the end of the list: PageResolver matches any single
 * lowercase segment, so it has to come after the literal routes it would
 * otherwise swallow. Same constraint routes/web.php lives under.
 */
class LinkResolvers
{
    private const RESOLVERS = [
        EntryResolver::class,
        StoryResolver::class,
        TagResolver::class,
        LiveResolver::class,
        PeriodResolver::class,
        ArchiveResolver::class,
        PageResolver::class,
    ];

    public function resolve(string $path): ?LinkPreviewData
    {
        foreach (self::RESOLVERS as $resolver) {
            $preview = app($resolver)->resolve($path);

            if ($preview !== null) {
                return $preview;
            }
        }

        return null;
    }
}
