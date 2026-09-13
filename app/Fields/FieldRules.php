<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;
use App\Rules\NotReservedSlug;
use App\Rules\TextOrDocument;
use Illuminate\Contracts\Validation\ValidationRule;

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
            if ($field->readOnly) {
                $rules[$field->name] = ['exclude'];

                continue;
            }

            if ($field->type->isMedia()) {
                // A `url:` item carries a full URL rather than an attached uuid.
                $rules[$field->name.'.*'] = ['string', 'max:2048'];
            }

            if ($field->type === FieldType::Tags) {
                $rules[$field->name.'.*'] = ['string', 'max:100'];
            }

            $rules[$field->name] = [
                ...self::presenceRules($field, $creating),
                ...self::typeRules($field),
            ];
        }

        return $rules;
    }

    /**
     * Whether a field must be filled.
     *
     * Only the genuinely mandatory fields are required, and only on create: an
     * update may touch one field and leave the rest alone. A date stamped at
     * save is left empty by a draft. A conditionally required field is also held
     * to its condition on update whenever it is sent, so an edit cannot blank it.
     *
     * @return list<string>
     */
    private static function presenceRules(FieldData $field, bool $creating): array
    {
        if (! $field->required) {
            return ['sometimes'];
        }

        $unless = array_map(
            fn (string $name, array $values): string => 'required_unless:'.implode(',', [$name, ...$values]),
            array_keys($field->requiredUnless ?? []),
            $field->requiredUnless ?? [],
        );

        return match (true) {
            ! $creating => ['sometimes', ...$unless],
            $field->defaultsToNow => ['required_unless:status,draft'],
            $unless !== [] => $unless,
            default => ['required'],
        };
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
     * @return array<int, string|ValidationRule>
     */
    private static function typeRules(FieldData $field): array
    {
        return match ($field->type) {
            FieldType::Title, FieldType::Text => ['nullable', 'string', 'max:255'],
            FieldType::Textarea => ['nullable', 'string', 'max:5000'],
            FieldType::RichText => ['nullable', 'array'],
            // Blocks from the editor, or a plain string from anything that only
            // has one; the model normalises a string into a single block.
            FieldType::Prose => ['nullable', new TextOrDocument($field->max)],
            FieldType::Slug => [
                'nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                ...($field->checksReservedSlug ? [new NotReservedSlug] : []),
            ],
            FieldType::Url => ['nullable', 'url', 'max:500'],
            FieldType::DateTime => ['nullable', 'date'],
            FieldType::Number, FieldType::Duration, FieldType::Distance => ['nullable', 'numeric'],
            FieldType::Rating => ['nullable', 'integer', 'between:1,10'],
            FieldType::Boolean => ['boolean'],
            FieldType::Tags => ['array'],
            FieldType::Select => ['nullable', 'string', self::in($field)],
            FieldType::Status => ['string', self::in($field)],
            // Both resolve to a name the lookup filled in, which stays
            // editable afterwards, so neither is constrained to what the
            // source returned.
            FieldType::Lookup, FieldType::Location => ['nullable', 'string', 'max:255'],
            // An ordered list of media uuids, `pending:` upload tokens and
            // `url:` items; the items themselves are checked by itemRules() below.
            FieldType::Image, FieldType::BookCover => ['nullable', 'array', 'max:1'],
            FieldType::Gallery => ['nullable', 'array', 'max:'.self::MAX_GALLERY],
            FieldType::Citation => ['nullable', 'string', 'max:600'],
        };
    }

    private static function in(FieldData $field): string
    {
        return 'in:'.implode(',', array_column($field->options, 'value'));
    }
}
