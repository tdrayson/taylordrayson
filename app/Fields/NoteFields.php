<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;
use App\Models\Note;

/**
 * A note is a quick capture: the body is the whole point, and capped, because
 * past Note::MAX_LENGTH it is an article. The slug is offered rather than
 * demanded, since a note has no title to derive one from; left blank it comes
 * from the note's opening words (see Note::slugFrom).
 */
final class NoteFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('content', 'Note', FieldType::Prose, required: true, max: Note::MAX_LENGTH),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('photos', 'Photos', FieldType::Gallery, collection: 'photos'),
            FieldData::primary('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
            FieldData::primary('slug', 'Slug', FieldType::Slug, fallback: Note::FALLBACK_SLUG),
        ];
    }
}
