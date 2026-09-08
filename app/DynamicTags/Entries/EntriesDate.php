<?php

namespace App\DynamicTags\Entries;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\DateFormat;
use App\Enums\TimelineType;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;
use Carbon\CarbonInterface;

/**
 * Shared resolution for the first/latest entry-date tags. The two differ only
 * by sort direction, supplied by the concrete subclass.
 */
abstract class EntriesDate extends DynamicTag
{
    /** 'asc' for the earliest entry, 'desc' for the most recent. */
    abstract protected function direction(): string;

    public function group(): string
    {
        return 'Entries';
    }

    /**
     * @return list<TagOption>
     */
    public function options(): array
    {
        return [
            new TagOption('type', 'Type', array_column(TimelineType::cases(), 'value')),
            new TagOption('format', 'Format', array_column(DateFormat::cases(), 'value'), DateFormat::Date->value),
        ];
    }

    /**
     * Null when the type is unrecognised or has no entries at all.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): ?CarbonInterface
    {
        $query = TimelineEntry::query();

        if (isset($options['type'])) {
            $model = TypeRegistry::find($options['type'])['model'] ?? null;

            if ($model === null) {
                return null;
            }

            $query->where('timelineable_type', $model);
        }

        // first()?->occurred_at rather than value(), so the model cast applies
        // and a Carbon instance comes back rather than a raw string.
        return $query->orderBy('occurred_at', $this->direction())->first()?->occurred_at;
    }

    /**
     * Falls back to `DateFormat::Date` when the option is missing or unrecognised.
     *
     * @param  array<string, string>  $options
     */
    public function format(mixed $value, array $options): string
    {
        $format = DateFormat::tryFrom($options['format'] ?? '') ?? DateFormat::Date;

        return $format->apply($value);
    }
}
