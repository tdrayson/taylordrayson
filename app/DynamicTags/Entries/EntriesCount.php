<?php

namespace App\DynamicTags\Entries;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\StatsPeriod;
use App\Enums\TimelineType;
use App\Models\TimelineEntry;
use App\Support\Period;
use App\Timeline\TypeRegistry;

/** How many things are on the timeline, optionally of one type. */
class EntriesCount extends DynamicTag
{
    public function name(): string
    {
        return 'entries.count';
    }

    public function label(): string
    {
        return 'Entry count';
    }

    public function group(): string
    {
        return 'Entries';
    }

    /**
     * `from`/`to` are a free-form date range and win over `period` when both are given.
     *
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [
            new TagOption('type', 'Type', array_column(TimelineType::cases(), 'value')),
            new TagOption('period', 'Period', array_column(StatsPeriod::cases(), 'value'), StatsPeriod::AllTime->value),
            new TagOption('from', 'From'),
            new TagOption('to', 'To'),
            $this->iconOption(),
        ];
    }

    public function supportsIcon(): bool
    {
        return true;
    }

    /**
     * The chosen type, so the client can draw that type's glyph; a count
     * across every type has no single glyph to show, so its icon is omitted.
     *
     * @param  array<string, string>  $options
     */
    public function iconPayload(mixed $value, array $options): mixed
    {
        return ['type' => $options['type'] ?? null];
    }

    /**
     * Null when the type is unrecognised; otherwise the count is never null.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?int
    {
        $query = TimelineEntry::query();

        if (isset($options['type'])) {
            $model = TypeRegistry::find($options['type'])['model'] ?? null;

            if ($model === null) {
                return null;
            }

            $query->where('timelineable_type', $model);
        }

        return Period::from($options)->apply($query)->count();
    }
}
