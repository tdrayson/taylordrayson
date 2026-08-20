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
    case Title = 'title';
    case Text = 'text';
    case Textarea = 'textarea';
    case RichText = 'rich-text';
    case Slug = 'slug';
    case Url = 'url';
    case DateTime = 'datetime';
    case Number = 'number';
    case Duration = 'duration';
    case Distance = 'distance';
    case Boolean = 'boolean';
    case Published = 'published';
    case Select = 'select';
    case Tags = 'tags';
    case Lookup = 'lookup';
    case Location = 'location';
    case Image = 'image';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Title',
            self::Text => 'Text',
            self::Textarea => 'Long text',
            self::RichText => 'Rich text',
            self::Slug => 'Slug',
            self::Url => 'URL',
            self::DateTime => 'Date and time',
            self::Number => 'Number',
            self::Duration => 'Duration',
            self::Distance => 'Distance',
            self::Boolean => 'Toggle',
            self::Published => 'Publish state',
            self::Select => 'Choice',
            self::Tags => 'Tags',
            self::Lookup => 'Lookup',
            self::Location => 'Location',
            self::Image => 'Image',
            self::Gallery => 'Photos',
        };
    }

    /**
     * Whether this field holds uploaded media rather than a column value, which
     * means it is synced to a Media Library collection after the save rather
     * than passed through as an attribute.
     */
    public function isMedia(): bool
    {
        return $this === self::Image || $this === self::Gallery;
    }

    /**
     * Whether this field is the entry's body, which the long-form editor gives
     * the full width to. Only one field per type should be one.
     */
    public function isBody(): bool
    {
        return $this === self::RichText;
    }

    /**
     * Whether this field is the entry's heading, which the editor renders as a
     * large borderless input above everything rather than as a labelled field.
     */
    public function isTitle(): bool
    {
        return $this === self::Title;
    }

    /**
     * Whether this field decides public visibility. The editor draws it as the
     * save action itself rather than as a checkbox, so "Post" can never claim
     * an entry is live while it is still a draft.
     */
    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
