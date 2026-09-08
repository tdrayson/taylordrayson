<?php

namespace App\DynamicTags\Entries;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\DateFormat;
use App\Enums\TimelineType;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;
use Carbon\CarbonInterface;

/** When a type first appeared on the timeline. */
class EntriesFirst extends DynamicTag
{
    protected string $direction = 'asc';

    public function name(): string
    {
        return 'entries.first';
    }

    public function label(): string
    {
        return 'First entry';
    }

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
        return $query->orderBy('occurred_at', $this->direction)->first()?->occurred_at;
    }

    /**
     * @param  array<string, string>  $options
     */
    public function format(mixed $value, array $options): string
    {
        $format = DateFormat::tryFrom($options['format'] ?? '') ?? DateFormat::Date;

        return $format->apply($value);
    }
}
