<?php

namespace App\Enums;

/**
 * The input a field renders as in the authoring UI.
 *
 * A closed set the app defines, so it is safe to cast and to match on
 * exhaustively. The value is what the Vue side switches on to pick a component.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case RichText = 'rich-text';
    case Slug = 'slug';
    case Url = 'url';
    case DateTime = 'datetime';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case Tags = 'tags';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Long text',
            self::RichText => 'Rich text',
            self::Slug => 'Slug',
            self::Url => 'URL',
            self::DateTime => 'Date and time',
            self::Number => 'Number',
            self::Boolean => 'Toggle',
            self::Select => 'Choice',
            self::Tags => 'Tags',
        };
    }

    /**
     * Whether this field is the entry's body, which the long-form editor gives
     * the full width to. Only one field per type should be one.
     */
    public function isBody(): bool
    {
        return $this === self::RichText;
    }
}
