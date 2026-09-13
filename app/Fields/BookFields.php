<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\EntryStatus;
use App\Enums\FieldType;

/**
 * A book read. Unlike films and episodes, books have no Trakt equivalent, so
 * they are entered by hand and their details live in the meta column rather
 * than in columns of their own.
 *
 * Dotted names address meta keys, which the action merges rather than
 * overwriting, so a field added later does not wipe the ones already there.
 */
final class BookFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('title', 'Title', FieldType::Lookup, required: true, source: 'book'),
            FieldData::primary('meta.author', 'Author', FieldType::Text, required: true),
            FieldData::primary('occurred_at', 'Finished', FieldType::DateTime, required: true, defaultsToNow: true),
            FieldData::optional('started_at', 'Started', FieldType::DateTime),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
            FieldData::optional('rating', 'Rating', FieldType::Number),
            FieldData::optional('meta.year', 'Published', FieldType::Number),
            FieldData::optional('meta.isbn', 'ISBN', FieldType::Text),
            FieldData::primary('status', 'Status', FieldType::Status, EntryStatus::options()),
            FieldData::hidden('password', 'Password', FieldType::Text),
        ];
    }
}
