<?php

namespace App\Services\PetrolFinder;

/**
 * Standardises the inconsistent casing the PetrolFinder API returns (some
 * fields all-caps, some title-case) into readable title case, while keeping
 * fuel-forecourt acronyms and UK postcode fragments uppercase.
 */
class StationNormaliser
{
    /**
     * Tokens kept uppercase inside station names/addresses (e.g. "Godstone Road SF Connect").
     *
     * @var array<int, string>
     */
    private const ACRONYMS = ['SF', 'MFG', 'MWSA', 'BP', 'JET', 'LPG', 'HGV', 'EV', 'UK'];

    public static function name(?string $value): ?string
    {
        return self::titleCase($value);
    }

    public static function city(?string $value): ?string
    {
        return self::titleCase($value);
    }

    public static function address(?string $value): ?string
    {
        return self::titleCase($value);
    }

    /**
     * Title-case each word, preserving known acronyms and UK postcode fragments.
     */
    private static function titleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        return preg_replace_callback('/[\p{L}0-9\'.-]+/u', function (array $match): string {
            $word = $match[0];
            $upper = mb_strtoupper($word);

            if (in_array($upper, self::ACRONYMS, true) || self::isPostcodeFragment($word)) {
                return $upper;
            }

            return self::titleWord($word);
        }, $value);
    }

    /**
     * Capitalise the first letter of each hyphen-separated part, leaving
     * apostrophe suffixes lowercase ("SAINSBURY'S" -> "Sainsbury's").
     */
    private static function titleWord(string $word): string
    {
        return preg_replace_callback(
            '/(?<![\p{L}\'])\p{Ll}/u',
            fn (array $match): string => mb_strtoupper($match[0]),
            mb_strtolower($word),
        );
    }

    private static function isPostcodeFragment(string $word): bool
    {
        return preg_match('/^[A-Za-z]{1,2}[0-9][A-Za-z0-9]?$/', $word) === 1
            || preg_match('/^[0-9][A-Za-z]{2}$/', $word) === 1;
    }
}
