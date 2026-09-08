<?php

namespace App\DynamicTags\Entries;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Enums\TagDateFormat;
use App\Enums\TimelineType;
use App\Models\TimelineEntry;
use App\Timeline\TypeRegistry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\Relation;

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
            new TagOption('format', 'Format', array_column(TagDateFormat::cases(), 'value'), TagDateFormat::Date->value),
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

            $query->where('dataset', Relation::getMorphAlias($model));
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
        $format = TagDateFormat::tryFrom($options['format'] ?? '') ?? TagDateFormat::Date;

        return $format->apply($value);
    }
}
