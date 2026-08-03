<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * A podcast, interview or livestream appearance. The thumbnail is derived from
 * the video rather than uploaded, so there is no cover field.
 */
final class AppearanceFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('title', 'Title', FieldType::Text, required: true),
            FieldData::primary('show_name', 'Show', FieldType::Text),
            FieldData::primary('occurred_at', 'Date', FieldType::DateTime),
            FieldData::primary('type', 'Kind', FieldType::Select, null, [
                ['value' => 'podcast', 'label' => 'Podcast'],
                ['value' => 'interview', 'label' => 'Interview'],
                ['value' => 'livestream', 'label' => 'Livestream'],
            ]),
            FieldData::optional('url', 'Link', FieldType::Url),
            FieldData::optional('video_url', 'Video', FieldType::Url, 'A YouTube URL also supplies the thumbnail.'),
            FieldData::optional('audio_url', 'Audio', FieldType::Url),
            FieldData::optional('duration', 'Duration', FieldType::Number, 'Seconds.'),
            FieldData::optional('description', 'Description', FieldType::Textarea),
        ];
    }
}
