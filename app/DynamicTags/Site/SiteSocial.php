<?php

namespace App\DynamicTags\Site;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\Placement;
use Illuminate\Support\Str;

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
     * One choice per network listed in `identity.profiles`; no default, so an
     * author must pick one.
     *
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [
            new TagOption('network', 'Network', array_keys($this->profiles())),
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
        return isset($options['network']) ? ($this->profiles()[$options['network']] ?? null) : null;
    }

    /**
     * The owner's rel="me" profiles keyed by a slug of their label, so "GitHub"
     * is chosen as `network: github`.
     *
     * @return array<string, string>
     */
    private function profiles(): array
    {
        return collect(config('identity.profiles'))
            ->mapWithKeys(fn (array $profile): array => [Str::slug($profile['label']) => $profile['href']])
            ->all();
    }
}
