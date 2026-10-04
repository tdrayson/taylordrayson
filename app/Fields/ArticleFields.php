<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\EditorTab;
use App\Enums\EntryStatus;
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
            FieldData::primary('status', 'Status', FieldType::Status, EntryStatus::options(draftFirst: true)),
            FieldData::hidden('password', 'Password', FieldType::Text),
            FieldData::optional('excerpt', 'Summary', FieldType::Textarea, tab: EditorTab::Summary),
            FieldData::optional('cover', 'Cover image', FieldType::Image, collection: 'cover', tab: EditorTab::Summary),
            FieldData::optional('response_kind', 'Response', FieldType::Select, ResponseKind::options(), tab: EditorTab::Response),
            FieldData::optional('response_url', 'Responding to', FieldType::Url, showWhen: ['response_kind' => []], tab: EditorTab::Response),
            FieldData::optional('response_quote', 'Quote', FieldType::Citation, showWhen: ['response_url' => []], tab: EditorTab::Response),
            FieldData::optional('rsvp_value', 'Answer', FieldType::Select, RsvpValue::options(), showWhen: ['response_kind' => ['rsvp']], tab: EditorTab::Response),
            FieldData::primary('tags', 'Tags', FieldType::Tags, sidebar: true),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true, sidebar: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone', sidebar: true),
            FieldData::optional('slug', 'Slug', FieldType::Slug, checksReservedSlug: true, sidebar: true),
        ];
    }
}
