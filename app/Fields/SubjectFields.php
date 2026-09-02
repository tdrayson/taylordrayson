<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\FieldType;
use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;

/**
 * A person, pet, spot or thing: slug-routed and dateless, like a page, but
 * offering a different shape per kind (only a spot gets a location search).
 */
final class SubjectFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(SubjectKind $kind): array
    {
        return [
            FieldData::primary('name', 'Name', FieldType::Title, required: true),
            FieldData::primary('bio', 'Bio', FieldType::RichText),
            FieldData::optional('cover', 'Cover image', FieldType::Image, collection: 'cover'),
            FieldData::optional('category', 'Category', FieldType::Select, self::categoryOptions($kind)),
            ...self::locationFields($kind),
            FieldData::optional('meta', 'Facts', FieldType::Facts),
            FieldData::optional('identities', 'Identities', FieldType::Facts, options: [
                ['value' => 'platform', 'label' => 'Platform'],
                ['value' => 'value', 'label' => 'Value'],
            ]),
            FieldData::primary('slug', 'Slug', FieldType::Slug),
        ];
    }

    /**
     * A spot's coordinates come from picking a place; the search box itself has
     * nowhere on the row to save to (a spot's name is already the subject's
     * own title), so only the hidden latitude/longitude it fills are kept.
     *
     * @return list<FieldData>
     */
    private static function locationFields(SubjectKind $kind): array
    {
        if ($kind !== SubjectKind::Spot) {
            return [];
        }

        return [
            FieldData::optional('location', 'Location', FieldType::Location, source: 'place'),
            FieldData::hidden('latitude', 'Latitude', FieldType::Number),
            FieldData::hidden('longitude', 'Longitude', FieldType::Number),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function categoryOptions(SubjectKind $kind): array
    {
        return collect(SubjectCategory::forKind($kind))
            ->map(fn (SubjectCategory $category): array => ['value' => $category->value, 'label' => $category->label()])
            ->all();
    }
}
