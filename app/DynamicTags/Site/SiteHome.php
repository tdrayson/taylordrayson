<?php

namespace App\DynamicTags\Site;

use App\DynamicTags\DynamicTag;

/** Where I am based, as opposed to ambient.location, which follows me around. */
class SiteHome extends DynamicTag
{
    public function name(): string
    {
        return 'site.home';
    }

    public function label(): string
    {
        return 'Home';
    }

    public function group(): string
    {
        return 'Site';
    }

    /**
     * Ignores `$options`; the tag takes none.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?string
    {
        return config('site.home');
    }
}
