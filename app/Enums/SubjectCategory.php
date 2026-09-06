<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * The second level under a kind. Nullable on the model: people, pets and spots
 * often do not bother, while things almost always carry one.
 *
 * A reference enum, NOT a cast: the column is a plain string so a category
 * typed in on the page (a new sort of spot, say) is stored as it stands rather
 * than throwing on read. These cases are the suggestions, not the whole set.
 */
enum SubjectCategory: string
{
    case Family = 'family';
    case Friend = 'friend';
    case Colleague = 'colleague';
    case Dog = 'dog';
    case Cat = 'cat';
    case Pub = 'pub';
    case Cafe = 'cafe';
    case Trail = 'trail';
    case Venue = 'venue';
    case Car = 'car';
    case Bike = 'bike';
    case Watch = 'watch';
    case Camera = 'camera';
    case Computer = 'computer';

    public function label(): string
    {
        return match ($this) {
            self::Family => 'Family',
            self::Friend => 'Friend',
            self::Colleague => 'Colleague',
            self::Dog => 'Dog',
            self::Cat => 'Cat',
            self::Pub => 'Pub',
            self::Cafe => 'Cafe',
            self::Trail => 'Trail',
            self::Venue => 'Venue',
            self::Car => 'Car',
            self::Bike => 'Bike',
            self::Watch => 'Watch',
            self::Camera => 'Camera',
            self::Computer => 'Computer',
        };
    }

    public function kind(): SubjectKind
    {
        return match ($this) {
            self::Family, self::Friend, self::Colleague => SubjectKind::Person,
            self::Dog, self::Cat => SubjectKind::Pet,
            self::Pub, self::Cafe, self::Trail, self::Venue => SubjectKind::Spot,
            self::Car, self::Bike, self::Watch, self::Camera, self::Computer => SubjectKind::Thing,
        };
    }

    /** Entry line wording, delegated from the kind. */
    public function phrase(): ?string
    {
        return match ($this->kind()) {
            SubjectKind::Person, SubjectKind::Pet => 'With',
            SubjectKind::Spot => 'At',
            SubjectKind::Thing => null,
        };
    }

    /** @return list<self> */
    public static function forKind(SubjectKind $kind): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category): bool => $category->kind() === $kind,
        ));
    }

    /** Display text for a stored value, which may be one nothing enumerates. */
    public static function labelFor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value)?->label() ?? Str::ucfirst(str_replace('-', ' ', $value));
    }

    /** The stored form: lowercase and hyphenated, so casing never splits one category in two. */
    public static function normalise(?string $value): ?string
    {
        $slug = Str::slug((string) $value);

        return $slug === '' ? null : $slug;
    }
}
