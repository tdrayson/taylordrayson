<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * A note is a quick capture: the body is the whole point, so everything else
 * stays out of the way until asked for.
 */
final class NoteFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('content', 'Note', FieldType::Textarea, required: true),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::optional('slug', 'Slug', FieldType::Slug, 'Generated from the content when left blank.'),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, 'Where it was written.', source: 'timezone'),
        ];
    }
}
