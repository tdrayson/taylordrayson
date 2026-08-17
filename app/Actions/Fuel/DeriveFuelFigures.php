<?php

namespace App\Actions\Fuel;

/**
 * Fill in whichever of litres and price per litre was not supplied.
 */
final class DeriveFuelFigures
{
    /**
     * @param  array<string, mixed>  $attributes  What the form sent.
     * @param  array<string, mixed>  $current  Figures already on the row, empty when creating.
     * @return array<string, mixed>
     */
    public function __invoke(array $attributes, array $current = []): array
    {
        // Saving an unrelated field must not nudge litres by a rounding step.
        if (array_intersect(['cost', 'litres', 'price_per_litre'], array_keys($attributes)) === []) {
            return $attributes;
        }

        $cost = (float) ($attributes['cost'] ?? $current['cost'] ?? 0);

        // Only one is derived, or each would be built from the other's stale value.
        if (self::given($attributes, 'litres')) {
            $litres = (float) $attributes['litres'];

            if (! self::given($attributes, 'price_per_litre') && $litres > 0) {
                $attributes['price_per_litre'] = round($cost / $litres, 3);
            }

            return $attributes;
        }

        $perLitre = (float) ($attributes['price_per_litre'] ?? $current['price_per_litre'] ?? 0);

        if ($perLitre > 0) {
            $attributes['litres'] = round($cost / $perLitre, 3);
        }

        return $attributes;
    }

    /**
     * Whether a figure was supplied. An empty field arrives as null, meaning
     * "work it out".
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function given(array $attributes, string $key): bool
    {
        return array_key_exists($key, $attributes)
            && $attributes[$key] !== null
            && $attributes[$key] !== '';
    }
}
