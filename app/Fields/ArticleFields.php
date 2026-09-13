<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\EntryStatus;
use App\Enums\FieldType;

/**
 * A dated long-form piece. Unlike a page it is a timeline entry, so the date
 * decides its URL and is primary.
 */
final class ArticleFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('title', 'Title', FieldType::Title, required: true),
            FieldData::primary('content', 'Content', FieldType::RichText),
            FieldData::primary('status', 'Status', FieldType::Status, EntryStatus::options(draftFirst: true)),
            FieldData::hidden('password', 'Password', FieldType::Text),
            FieldData::optional('excerpt', 'Summary', FieldType::Textarea),
            FieldData::optional('cover', 'Cover image', FieldType::Image, collection: 'cover'),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
            FieldData::optional('slug', 'Slug', FieldType::Slug, checksReservedSlug: true),
        ];
    }
}
