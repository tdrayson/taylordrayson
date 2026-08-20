<?php

namespace App\Fields;

use App\Data\FieldData;
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
            FieldData::primary('published', 'Published', FieldType::Published),
            FieldData::optional('excerpt', 'Summary', FieldType::Textarea, FieldHelp::SUMMARY),
            FieldData::optional('cover', 'Cover image', FieldType::Image, collection: 'cover'),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, 'Part of the URL, so changing it moves the article.', defaultsToNow: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone', pairsWith: 'occurred_at'),
            FieldData::optional('slug', 'Slug', FieldType::Slug, 'Generated from the title when left blank.'),
        ];
    }
}
