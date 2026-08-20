<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * A standalone slug-routed page (/about, /colophon). Not a timeline entry, so
 * it carries no date at all.
 */
final class PageFields
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
            FieldData::primary('slug', 'Slug', FieldType::Slug),
        ];
    }
}
