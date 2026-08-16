<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * A note is a quick capture: the body is the whole point. The slug is asked for
 * rather than derived, since a note has no title to derive one from and a
 * generated one reads as noise in the URL.
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
            FieldData::primary('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone', pairsWith: 'occurred_at'),
            FieldData::primary('slug', 'Slug', FieldType::Slug, 'The URL this note lives at.', required: true),
        ];
    }
}
