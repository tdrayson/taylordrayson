<?php

namespace App\Fields;

use App\Data\FieldData;
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
            FieldData::optional('long_description', 'About', FieldType::RichText),
            FieldData::optional('github_url', 'Repository', FieldType::Url),
            FieldData::optional('featured', 'Featured', FieldType::Boolean),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('cover', 'Cover image', FieldType::Image, collection: 'cover'),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
            FieldData::optional('slug', 'Slug', FieldType::Slug, checksReservedSlug: true),
        ];
    }
}
