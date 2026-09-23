<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Sqids\Sqids;

/**
 * The serial printed on a ticket or receipt: the entry's occurred_at encoded
 * with Sqids.
 *
 * It is decorative, but not arbitrary. Encoding rather than printing the raw
 * timestamp solves the thing a timestamp cannot: every epoch this decade
 * opens "17...", so a wall of them reads as the same number. Sqids scatters
 * adjacent inputs completely (one second apart shares nothing), while staying
 * reversible, so the moment is still recoverable from the printed code.
 */
final class SerialNumber
{
    /**
     * Frozen: these values are printed on published pages, so changing either
     * silently renumbers every ticket and receipt on the site.
     *
     * The alphabet is upper-case for a printed reference, and omits I, O, 0
     * and 1 so a code cannot be misread when copied by hand.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    public static function for(?CarbonInterface $occurredAt): string
    {
        return self::sqids()->encode([$occurredAt?->getTimestamp() ?? 0]);
    }

    /** The moment a serial was made from, or null if it does not decode. */
    public static function moment(string $serial): ?CarbonInterface
    {
        $decoded = self::sqids()->decode($serial);

        return $decoded === [] ? null : CarbonImmutable::createFromTimestamp($decoded[0]);
    }

    private static function sqids(): Sqids
    {
        return new Sqids(alphabet: self::ALPHABET, minLength: self::LENGTH);
    }
}
