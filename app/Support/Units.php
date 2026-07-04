<?php

namespace App\Support;

class Units
{
    /**
     * Normalise a duration input to integer seconds. Accepts integers/floats
     * (already seconds), numeric strings, "2h 56m" style component strings,
     * and "H:MM:SS" / "MM:SS" clock strings. Returns null when unparseable.
     */
    public static function seconds(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $value, $m)) {
            return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
        }

        $seconds = 0;
        $matched = false;
        $pattern = '/(\d+(?:\.\d+)?)\s*(h|hr|hrs|hour|hours|m|min|mins|minute|minutes|s|sec|secs|second|seconds)/';

        if (preg_match_all($pattern, $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $amount = (float) $match[1];
                $seconds += match ($match[2][0]) {
                    'h' => $amount * 3600,
                    'm' => $amount * 60,
                    's' => $amount,
                };
                $matched = true;
            }
        }

        return $matched ? (int) round($seconds) : null;
    }

    /**
     * Normalise a distance input to integer metres. Accepts numbers (already
     * metres), numeric strings, and "5.2 km" / "774 miles" / "1200 m" style
     * strings. Returns null when unparseable.
     */
    public static function metres(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*(km|kilometre|kilometres|kilometer|kilometers|mi|mile|miles|m|metre|metres|meter|meters)$/', $value, $m)) {
            return null;
        }

        $amount = (float) $m[1];

        return match ($m[2][0].($m[2][1] ?? '')) {
            'km', 'ki' => Distance::fromKm($amount),
            'mi' => Distance::fromMiles($amount),
            default => (int) round($amount),
        };
    }
}
