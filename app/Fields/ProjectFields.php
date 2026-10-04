<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\EditorTab;
use App\Enums\EntryStatus;
use App\Enums\FieldType;
use App\Enums\ProjectStage;

/** A project's stage is its real-world lifecycle, not a publishing state. */
final class ProjectFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('title', 'Title', FieldType::Title, required: true),
            FieldData::primary('description', 'Summary', FieldType::Textarea, required: true),
            FieldData::primary('stage', 'Stage', FieldType::Select, array_map(
                fn (ProjectStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()],
                ProjectStage::cases(),
            ), required: true),
            FieldData::primary('url', 'Link', FieldType::Url),
            FieldData::optional('long_description', 'About', FieldType::RichText, tab: EditorTab::Summary),
            FieldData::optional('github_url', 'Repository', FieldType::Url),
            FieldData::optional('featured', 'Featured', FieldType::Boolean, sidebar: true),
            FieldData::primary('tags', 'Tags', FieldType::Tags, sidebar: true),
            FieldData::optional('cover', 'Cover image', FieldType::Image, collection: 'cover', sidebar: true),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true, sidebar: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone', sidebar: true),
            FieldData::optional('slug', 'Slug', FieldType::Slug, checksReservedSlug: true, sidebar: true),
            FieldData::primary('status', 'Status', FieldType::Status, EntryStatus::options()),
            FieldData::hidden('password', 'Password', FieldType::Text),
        ];
    }
}
