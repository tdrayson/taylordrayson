<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;

/**
 * Validation derived from the field definitions.
 *
 * The same declaration that draws the form validates it, so a field cannot be
 * offered in the UI and silently rejected on save, or accepted without ever
 * having been declared. Per-type Form Requests still exist for the endpoints
 * with their own contract (the API, Setgraph); this is for the generic editor.
 */
final class FieldRules
{
    private const MAX_GALLERY = 24;

    /**
     * @param  list<FieldData>  $fields
     * @return array<string, array<int, string>>
     */
    public static function for(array $fields, bool $creating): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field->type->isMedia()) {
                $rules[$field->name.'.*'] = ['string', 'max:100'];
            }

            $rules[$field->name] = [
                // Only the genuinely mandatory fields are required, and only on
                // create: an update may touch one field and leave the rest
                // alone. Visibility (primary) is a separate question.
                $creating && $field->required ? 'required' : 'sometimes',
                ...self::typeRules($field),
            ];
        }

        return $rules;
    }

    /**
     * The label each field is drawn with, so a message names what the form calls
     * it: "The link field must be a valid URL", not "The url field...".
     *
     * @param  list<FieldData>  $fields
     * @return array<string, string>
     */
    public static function labels(array $fields): array
    {
        return array_column(
            array_map(fn (FieldData $field): array => [$field->name, strtolower($field->label)], $fields),
            1,
            0,
        );
    }

    /**
     * @return array<int, string>
     */
    private static function typeRules(FieldData $field): array
    {
        return match ($field->type) {
            FieldType::Title, FieldType::Text => ['nullable', 'string', 'max:255'],
            FieldType::Textarea => ['nullable', 'string', 'max:5000'],
            FieldType::RichText => ['nullable', 'array'],
            FieldType::Slug => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            FieldType::Url => ['nullable', 'url', 'max:500'],
            FieldType::DateTime => ['nullable', 'date'],
            FieldType::Number, FieldType::Duration, FieldType::Distance => ['nullable', 'numeric'],
            FieldType::Boolean, FieldType::Published => ['boolean'],
            FieldType::Tags => ['array'],
            FieldType::Select => ['nullable', 'string', self::in($field)],
            // Both resolve to a name the lookup filled in, which stays
            // editable afterwards, so neither is constrained to what the
            // source returned.
            FieldType::Lookup, FieldType::Location => ['nullable', 'string', 'max:255'],
            // An ordered list of media uuids and `pending:` upload tokens; the
            // items themselves are checked by itemRules() below.
            FieldType::Image => ['nullable', 'array', 'max:1'],
            FieldType::Gallery => ['nullable', 'array', 'max:'.self::MAX_GALLERY],
        };
    }

    private static function in(FieldData $field): string
    {
        return 'in:'.implode(',', array_column($field->options, 'value'));
    }
}
