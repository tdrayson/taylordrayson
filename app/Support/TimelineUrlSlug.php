<?php

namespace App\Support;

use App\Models\TimelineEntry;

/**
 * Assigns a timeline entry's URL slug once at write time: the first same-day
 * holder of a base slug keeps it bare, collisions get -2/-3... Stored so URLs
 * never reshuffle when earlier entries are backfilled or deleted; only a
 * changed base slug or a date move triggers reassignment.
 */
class TimelineUrlSlug
{
    /**
     * Slug => the only dataset key allowed to hold it bare: these words are
     * also fixed day-URLs (/food, /sleep), so no other entry may borrow them.
     */
    private const RESERVED = [
        'food' => 'food',
        'sleep' => 'sleep',
    ];

    public static function ensure(TimelineEntry $entry, string $base): void
    {
        $pattern = '/^'.preg_quote($base, '/').'(-\d+)?$/';
        $reservedForOther = self::reservedForOther($base, $entry->dataset);

        if ($entry->url_slug !== null
            && preg_match($pattern, $entry->url_slug) === 1
            && ! ($reservedForOther && $entry->url_slug === $base)
            && ! self::takenByAnother($entry, $entry->url_slug)) {
            return;
        }

        $taken = TimelineEntry::query()
            ->whereKeyNot($entry->getKey())
            ->whereDate('occurred_at', $entry->occurred_at->toDateString())
            ->where(fn ($query) => $query->where('url_slug', $base)->orWhere('url_slug', 'like', "{$base}-%"))
            ->pluck('url_slug')
            ->filter(fn (?string $slug): bool => $slug !== null && preg_match($pattern, $slug) === 1)
            ->all();

        if ($reservedForOther) {
            $taken[] = $base;
        }

        $candidate = $base;
        $suffix = 1;

        while (in_array($candidate, $taken, true)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        $entry->url_slug = $candidate;
        $entry->save();
    }

    private static function reservedForOther(string $base, ?string $dataset): bool
    {
        return isset(self::RESERVED[$base]) && self::RESERVED[$base] !== $dataset;
    }

    private static function takenByAnother(TimelineEntry $entry, string $slug): bool
    {
        return TimelineEntry::query()
            ->whereKeyNot($entry->getKey())
            ->whereDate('occurred_at', $entry->occurred_at->toDateString())
            ->where('url_slug', $slug)
            ->exists();
    }
}
