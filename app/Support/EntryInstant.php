<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * The instant a local wall-clock reading actually happened.
 *
 * `occurred_at` holds the clock as it read where the entry happened, so two
 * entries reading 09:00 in London and New York are five hours apart despite
 * sorting as equal. This is what `timeline_entries.occurred_utc` stores so the
 * timeline can order by when things happened rather than by what the clock said.
 */
class EntryInstant
{
    /** Where an entry with no zone of its own is assumed to have happened. */
    public const HOME = 'Europe/London';

    /**
     * @param  mixed  $occurredAt  The local wall-clock reading.
     * @param  string|null  $timezone  Its IANA zone; null falls back to home.
     */
    public static function utc(mixed $occurredAt, ?string $timezone): ?Carbon
    {
        if (blank($occurredAt)) {
            return null;
        }

        $local = $occurredAt instanceof Carbon
            ? $occurredAt->format('Y-m-d H:i:s')
            : (string) $occurredAt;

        try {
            // Parsed as a naive reading in the entry's own zone, never in the
            // server's: the stored string carries no offset of its own.
            return Carbon::parse($local, self::zone($timezone))->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** A usable IANA zone, falling back to home for anything unrecognised. */
    public static function zone(?string $timezone): string
    {
        if (blank($timezone)) {
            return self::HOME;
        }

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : self::HOME;
    }
}
