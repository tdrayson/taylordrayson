<?php

namespace App\Data;

/**
 * Coercion helpers shared by the `meta` DTOs.
 *
 * JSON columns hand back whatever was written years ago: an episode number
 * might be `12` or `"12"`, and a key that was explicitly nulled reads the same
 * as one that was never set. These normalise both so the DTOs can promise
 * their declared types.
 */
final class MetaValue
{
    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * A number kept as the type it was written as, so a whole-numbered rating
     * round-trips as `8` rather than becoming `8.0` and comparing unequal to
     * the `8` the API sends back next run.
     */
    public static function number(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        return ((float) $value) === floor((float) $value) ? (int) $value : (float) $value;
    }

    /** Blank strings are treated as absent, not as a value. */
    public static function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        return trim((string) $value) ?: null;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): ?string => self::string($item),
            $value,
        )));
    }

    /**
     * Drop null and empty entries so a DTO round-trips to the shape it was
     * built from: a key the source never set must not reappear as `null`.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function compact(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '',
        );
    }
}
