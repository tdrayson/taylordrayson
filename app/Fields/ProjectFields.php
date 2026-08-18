<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * Status here is the project's real-world state, not a publish gate: a project
 * has no draft state, so it never appears in /drafts.
 */
final class ProjectFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('title', 'Title', FieldType::Title, required: true),
            FieldData::primary('description', 'Summary', FieldType::Textarea, FieldHelp::SUMMARY, required: true),
            FieldData::primary('status', 'Status', FieldType::Select, null, [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'maintained', 'label' => 'Maintained'],
                ['value' => 'on_hold', 'label' => 'On hold'],
                ['value' => 'archived', 'label' => 'Archived'],
            ], required: true),
            FieldData::primary('url', 'Link', FieldType::Url),
            FieldData::optional('long_description', 'About', FieldType::RichText),
            FieldData::optional('github_url', 'Repository', FieldType::Url),
            FieldData::optional('featured', 'Featured', FieldType::Boolean),
            FieldData::primary('tags', 'Tags', FieldType::Tags),
            FieldData::optional('occurred_at', 'Date', FieldType::DateTime, defaultsToNow: true),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone', pairsWith: 'occurred_at'),
            FieldData::optional('slug', 'Slug', FieldType::Slug),
        ];
    }
}
