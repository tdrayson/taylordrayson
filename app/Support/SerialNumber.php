<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The serial printed on a ticket or receipt: the entry's occurred_at as a
 * Unix timestamp.
 *
 * Decorative. It exists so the number means something rather than reading as
 * random, not to identify anything, so it is not required to be unique: two
 * entries logged at the same second share one, and nothing depends on it.
 */
final class SerialNumber
{
    public static function for(?CarbonInterface $occurredAt): string
    {
        return (string) ($occurredAt?->getTimestamp() ?? 0);
    }
}
