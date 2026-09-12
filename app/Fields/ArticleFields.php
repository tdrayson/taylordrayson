<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;
use App\Enums\ResponseKind;
use App\Enums\RsvpValue;

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
            FieldData::optional('excerpt', 'Summary', FieldType::Textarea),
            FieldData::optional('cover', 'Cover image', FieldType::Image, collection: 'cover'),
            FieldData::optional('response_kind', 'Response', FieldType::Select, ResponseKind::options()),
            FieldData::optional('response_url', 'Responding to', FieldType::Url, showWhen: ['response_kind' => []]),
            FieldData::optional('rsvp_value', 'Answer', FieldType::Select, RsvpValue::options(), showWhen: ['response_kind' => ['rsvp']]),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
            FieldData::optional('slug', 'Slug', FieldType::Slug),
        ];
    }
}
