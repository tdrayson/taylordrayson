<?php

namespace App\Support;

/**
 * The timeline data-type accent colours, parsed from the `--color-{type}` custom
 * properties in resources/css/theme.css and converted to hex for Mapbox overlays
 * and the OG cards, so there is no second hand-maintained list.
 *
 * theme.css rather than dark.css, which redefines the same tokens: an OG card
 * and a map overlay are both drawn on a light ground whatever the reader's
 * theme is.
 */
class TypeColors
{
    /** @var array<string, string>|null token => 6-digit hex */
    private static ?array $colors = null;

    private const FALLBACK = '3858e9';

    /** Where the light palette is declared. */
    private const SOURCE = 'resources/css/theme.css';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$colors !== null) {
            return self::$colors;
        }

        $css = @file_get_contents(base_path(self::SOURCE)) ?: '';

        // Single-word `--color-{token}: hsl(h s% l%)` declarations only, which is
        // exactly the data-type accents (neutrals, scales, and stages are dashed).
        preg_match_all(
            '/--color-([a-z]+):\s*hsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%\s*\)/i',
            $css,
            $matches,
            PREG_SET_ORDER,
        );

        $colors = [];

        foreach ($matches as $match) {
            $colors[$match[1]] = self::hslToHex((float) $match[2], (float) $match[3], (float) $match[4]);
        }

        return self::$colors = $colors;
    }

    /**
     * The hex for a card accent token (e.g. 'activity', 'food'), or the brand
     * default when the token has no colour.
     */
    public static function hex(string $token, string $default = self::FALLBACK): string
    {
        return self::all()[$token] ?? $default;
    }

    /**
     * Convert HSL colour components to a 6-digit lowercase hex string.
     *
     * @param  float  $hue  Hue in degrees, [0, 360).
     * @param  float  $saturation  Saturation as a percentage, [0, 100].
     * @param  float  $lightness  Lightness as a percentage, [0, 100].
     */
    private static function hslToHex(float $hue, float $saturation, float $lightness): string
    {
        $saturation /= 100;
        $lightness /= 100;

        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $intermediate = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $lightnessOffset = $lightness - $chroma / 2;

        [$red, $green, $blue] = match (true) {
            $hue < 60 => [$chroma, $intermediate, 0],
            $hue < 120 => [$intermediate, $chroma, 0],
            $hue < 180 => [0, $chroma, $intermediate],
            $hue < 240 => [0, $intermediate, $chroma],
            $hue < 300 => [$intermediate, 0, $chroma],
            default => [$chroma, 0, $intermediate],
        };

        return sprintf(
            '%02x%02x%02x',
            (int) round(($red + $lightnessOffset) * 255),
            (int) round(($green + $lightnessOffset) * 255),
            (int) round(($blue + $lightnessOffset) * 255),
        );
    }
}
