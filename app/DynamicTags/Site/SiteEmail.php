<?php

namespace App\DynamicTags\Site;

use App\DynamicTags\DynamicTag;
use App\Enums\Placement;

/** My email address, with the `@` spelled out against harvesters. */
class SiteEmail extends DynamicTag
{
    public function name(): string
    {
        return 'site.email';
    }

    public function label(): string
    {
        return 'Email address';
    }

    public function group(): string
    {
        return 'Site';
    }

    /**
     * Inline only: the spelled-out form is not a working mailto: target, so
     * unlike site.social this tag cannot sit behind a link.
     *
     * @return list<Placement>
     */
    public function supports(): array
    {
        return [Placement::Inline];
    }

    /**
     * `@` replaced with `(at)`, so the rendered page, the feeds, OG
     * descriptions and the search index never carry a harvestable address.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?string
    {
        $address = config('site.email');

        return $address === null ? null : str_replace('@', '(at)', $address);
    }
}
