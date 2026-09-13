<?php

namespace App\Enums;

use Illuminate\Database\Eloquent\Builder;

/**
 * The /photos gallery's work-queue facets: photographs still missing this
 * data and not dismissed. No case means "Everything".
 */
enum PhotoFilter: string
{
    case NeedsTagging = 'needs-tagging';
    case NeedsAlt = 'needs-alt';

    public function label(): string
    {
        return match ($this) {
            self::NeedsTagging => 'Needs tagging',
            self::NeedsAlt => 'Needs alt text',
        };
    }

    /**
     * Narrows an Attachment query to photographs still missing this facet's
     * data, shared by PhotoStream and the filter bar's counts so the two
     * cannot disagree on what "needs" means.
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::NeedsTagging => $query->whereDoesntHave('subjects')
                ->whereNull('custom_properties->'.str_replace('.', '->', ReviewKind::Subjects->property())),
            self::NeedsAlt => $query->whereNull('custom_properties->alt'),
        };
    }
}
