<?php

namespace App\DynamicTags\Site;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\Placement;

/** A profile URL for one network, so a moved account is changed in one place. */
class SiteSocial extends DynamicTag
{
    public function name(): string
    {
        return 'site.social';
    }

    public function label(): string
    {
        return 'Social link';
    }

    public function group(): string
    {
        return 'Site';
    }

    /**
     * Also legal as a link target, unlike most tags which are inline-only.
     *
     * @return list<Placement>
     */
    public function supports(): array
    {
        return [Placement::Inline, Placement::Href];
    }

    /**
     * One choice per network configured in `site.social`.
     *
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [
            new TagOption('network', 'Network', array_keys(config('site.social')), 'github'),
        ];
    }

    /**
     * Falls back to github when the network option is missing.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?string
    {
        return config('site.social.'.($options['network'] ?? 'github'));
    }
}
