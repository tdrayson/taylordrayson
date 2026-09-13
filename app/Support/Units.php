<?php

namespace App\Support;

use Illuminate\Support\Str;

class Units
{
    /**
     * Normalise a duration input to integer seconds. Accepts integers/floats
     * (already seconds), numeric strings, "2h 56m" style component strings,
     * and "H:MM:SS" / "MM:SS" clock strings. Returns null when unparseable.
     */
    /**
     * Format a duration in seconds as "9h 21m", dropping a zero minute ("9h")
     * and the hour when there is none ("45m").
     *
     * ActivityCard keeps its own zero-padded variant ("1h 05m"), which reads as
     * a race time rather than a rough length; this is the prose form.
     */
    public static function humanDuration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours === 0) {
            return "{$remainder}m";
        }

        return $remainder > 0 ? "{$hours}h {$remainder}m" : "{$hours}h";
    }

    /**
     * The same duration in words: "9 hours 21 minutes". For an accessible name,
     * where "9h 21m" is read out a letter at a time.
     */
    public static function spokenDuration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        $parts = array_filter([
            $hours > 0 ? $hours.' '.Str::plural('hour', $hours) : null,
            $remainder > 0 || $hours === 0 ? $remainder.' '.Str::plural('minute', $remainder) : null,
        ]);

        return implode(' ', $parts);
    }

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
        $pattern = '/(\d+(?:\.\d+)?)\s*(h|hr|hrs|hour|hours|m|min|mins|minute|minutes|s|sec|secs|second|seconds)(?![a-z])/';

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
     * A fuel price in pence, the way a forecourt board shows it: £1.619 a
     * litre is "161.9p".
     *
     * Always to a tenth of a penny, because that is the unit fuel is sold in,
     * and the reason this one price does not go through the two-decimal money
     * formatter. Null for a blank value, so a caller can drop the line.
     */
    public static function pencePerLitre(mixed $pounds): ?string
    {
        if ($pounds === null || $pounds === '') {
            return null;
        }

        return number_format((float) $pounds * 100, 1).'p';
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
