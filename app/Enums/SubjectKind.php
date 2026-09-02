<?php

namespace App\Enums;

/**
 * The four sorts of subject. Cast on the model, since only the app mints these.
 */
enum SubjectKind: string
{
    case Person = 'person';
    case Pet = 'pet';
    case Spot = 'spot';
    case Thing = 'thing';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Person',
            self::Pet => 'Pet',
            self::Spot => 'Spot',
            self::Thing => 'Thing',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Person => 'People',
            self::Pet => 'Pets',
            self::Spot => 'Spots',
            self::Thing => 'Things',
        };
    }

    /** The URL word, which is the plural lowercased. */
    public function segment(): string
    {
        return strtolower($this->plural());
    }

    /**
     * The wording an entry line uses when the subject's category has none.
     * A thing returns null: an entry cannot tell whether a car in a photograph
     * was driven or merely parked there, so it claims neither.
     */
    public function phrase(): ?string
    {
        return match ($this) {
            self::Person, self::Pet => 'With',
            self::Spot => 'At',
            self::Thing => null,
        };
    }

    public static function fromSegment(string $segment): ?self
    {
        foreach (self::cases() as $kind) {
            if ($kind->segment() === $segment) {
                return $kind;
            }
        }

        return null;
    }
}
