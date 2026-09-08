<?php

namespace App\DynamicTags\Site;

use App\DynamicTags\DynamicTag;
use App\Enums\Placement;

/** My email address, as text or behind a mailto link. */
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
     * Also legal as a link target, unlike most tags which are inline-only.
     *
     * @return list<Placement>
     */
    public function supports(): array
    {
        return [Placement::Inline, Placement::Href];
    }

    /**
     * Ignores `$options`; the tag takes none.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?string
    {
        return config('site.email');
    }
}
