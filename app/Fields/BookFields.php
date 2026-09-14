<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\EntryStatus;
use App\Enums\FieldType;
use App\Enums\Source;
use App\Models\Book;

/**
 * A book, read or in progress. A Kindle book's progress belongs to the sync,
 * so it is shown read-only; a manual book is tracked by page instead.
 */
final class BookFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(?Book $book = null): array
    {
        $progress = $book?->source === Source::Kindle->value
            ? [
                FieldData::readOnly('progress', 'Progress', FieldType::Number, suffix: '%'),
                FieldData::readOnly('source_id', 'Kindle ID', FieldType::Text),
            ]
            : [
                FieldData::optional('current_page', 'Page', FieldType::Number),
                FieldData::optional('pages', 'Pages', FieldType::Number),
            ];

        return [
            FieldData::primary('title', 'Title', FieldType::Lookup, required: true, source: 'book'),
            FieldData::primary('meta.author', 'Author', FieldType::Text),
            FieldData::optional('meta.subtitle', 'Subtitle', FieldType::Text),
            FieldData::primary('cover', 'Cover', FieldType::BookCover, collection: 'cover'),
            ...$progress,
            FieldData::primary('occurred_at', 'Finished', FieldType::DateTime, required: true, defaultsToNow: true),
            FieldData::optional('started_at', 'Started', FieldType::DateTime),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
            FieldData::optional('rating', 'Rating', FieldType::Number),
            FieldData::optional('overview', 'Overview', FieldType::Textarea),
            FieldData::optional('meta.year', 'Published', FieldType::Number),
            FieldData::optional('meta.isbn', 'ISBN', FieldType::Text),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::primary('status', 'Status', FieldType::Status, EntryStatus::options()),
            FieldData::hidden('password', 'Password', FieldType::Text),
        ];
    }
}
