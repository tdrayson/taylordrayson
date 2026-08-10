<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * An event is a public occasion attended as part of a crowd. Its category is a
 * tag rather than a column (the type column was dropped when events moved to
 * tags), so Tags is primary here and does real work.
 */
final class EventFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('name', 'Name', FieldType::Title, required: true),
            FieldData::primary('occurred_at', 'Starts', FieldType::DateTime, defaultsToNow: true),
            FieldData::primary('venue_name', 'Venue', FieldType::Location, 'Search, or use your location.', source: 'place'),
            FieldData::primary('tags', 'Category', FieldType::Tags, 'The first tag is shown as the category.'),
            FieldData::optional('ends_at', 'Ends', FieldType::DateTime, 'Set for multi-day events; shows a date range.', relativeTo: 'occurred_at'),
            FieldData::optional('all_day', 'All day', FieldType::Boolean),
            FieldData::optional('organiser', 'Organiser', FieldType::Text, 'Only shown when it differs from the name.'),
            FieldData::optional('city', 'City', FieldType::Text, group: 'Address'),
            FieldData::optional('country', 'Country', FieldType::Text, group: 'Address'),
            FieldData::hidden('latitude', 'Latitude', FieldType::Number),
            FieldData::hidden('longitude', 'Longitude', FieldType::Number),
            FieldData::optional('url', 'Link', FieldType::Url),
            FieldData::optional('description', 'Notes', FieldType::Textarea),
            FieldData::optional('timezone', 'Timezone', FieldType::Lookup, source: 'timezone'),
        ];
    }
}
