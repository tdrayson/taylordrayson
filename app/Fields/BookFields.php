<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\EditorTab;
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
                FieldData::readOnly('percent_read', 'Progress', FieldType::Number, suffix: '%'),
                FieldData::readOnly('source_id', 'Kindle ID', FieldType::Text),
            ]
            : [
                FieldData::optional('current_page', 'Page', FieldType::Number),
                FieldData::optional('pages', 'Pages', FieldType::Number),
            ];

        return [
            FieldData::primary('title', 'Title', FieldType::Lookup, required: true, source: 'book'),
            FieldData::primary('meta.author', 'Author', FieldType::Text),
            FieldData::primary('cover', 'Cover', FieldType::BookCover, collection: 'cover'),
            ...$progress,
            FieldData::primary('occurred_at', 'Finished', FieldType::DateTime, required: true, defaultsToNow: true),
            FieldData::optional('started_at', 'Started', FieldType::DateTime),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone', sidebar: true),
            FieldData::optional('rating', 'Rating', FieldType::Rating, suffix: '/10'),
            FieldData::optional('overview', 'Overview', FieldType::Textarea, tab: EditorTab::Details),
            FieldData::optional('meta.year', 'Publication year', FieldType::Number, tab: EditorTab::Details),
            FieldData::optional('meta.isbn', 'ISBN', FieldType::Text, tab: EditorTab::Details),
            FieldData::primary('tags', 'Tags', FieldType::Tags, sidebar: true),
            FieldData::primary('status', 'Status', FieldType::Status, EntryStatus::options()),
            FieldData::hidden('password', 'Password', FieldType::Text),
        ];
    }
}
