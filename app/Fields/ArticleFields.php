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
            FieldData::primary('published', 'Published', FieldType::Boolean, 'Drafts are visible only to you and appear in /drafts.'),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('excerpt', 'Excerpt', FieldType::Textarea, 'Used for search and social previews.'),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, 'Sets the URL.', defaultsToNow: true),
            FieldData::optional('slug', 'Slug', FieldType::Slug, 'Generated from the title when left blank.'),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
        ];
    }
}
