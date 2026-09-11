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
     * One choice per network configured in `site.social`; no default, so an
     * author must pick one.
     *
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [
            new TagOption('network', 'Network', array_keys(config('site.social'))),
        ];
    }

    /**
     * Null when the network option is missing or unrecognised, same as an
     * unknown network.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?string
    {
        return isset($options['network']) ? config('site.social.'.$options['network']) : null;
    }

    /** No default network, so a null resolve means one was never chosen, not that data is missing. */
    public function needsOption(): bool
    {
        return true;
    }
}
