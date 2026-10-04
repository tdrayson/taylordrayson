<?php

namespace App\DynamicTags\Site;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\TagDateFormat;
use App\Models\TimelineEntry;
use Carbon\CarbonInterface;

/** When data last arrived, which is when the site last changed by itself. */
class SiteUpdated extends DynamicTag
{
    public function name(): string
    {
        return 'site.updated';
    }

    public function label(): string
    {
        return 'Last updated';
    }

    public function group(): string
    {
        return 'Site';
    }

    /**
     * `format` selects a {@see TagDateFormat} rendering; defaults to relative.
     *
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [
            new TagOption('format', 'Format', array_column(TagDateFormat::cases(), 'value'), TagDateFormat::Relative->value),
        ];
    }

    /**
     * Null when no timeline entry exists yet.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?CarbonInterface
    {
        return TimelineEntry::query()->latest('created_at')->first()?->created_at;
    }

    /**
     * Falls back to `TagDateFormat::Relative` when the option is missing or unrecognised.
     *
     * @param  array<string, string>  $options
     */
    public function format(mixed $value, array $options): string
    {
        $format = TagDateFormat::tryFrom($options['format'] ?? '') ?? TagDateFormat::Relative;

        return $format->apply($value);
    }
}
