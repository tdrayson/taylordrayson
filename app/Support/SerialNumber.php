<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The serial printed on a ticket or receipt: the entry's date, then its id.
 *
 * The date carries the meaning (a reader can see when it was), the id keeps
 * it unique. A bare timestamp cannot: several food items are logged in the
 * same second, so thousands of days would otherwise share a receipt number.
 */
final class SerialNumber
{
    public static function for(?CarbonInterface $occurredAt, int $id, int $pad = 4): string
    {
        $sequence = str_pad((string) $id, $pad, '0', STR_PAD_LEFT);

        return $occurredAt === null
            ? $sequence
            : $occurredAt->format('Ymd').'-'.$sequence;
    }
}
