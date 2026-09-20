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

    /**
     * The serial for something there is exactly one of per day, such as a
     * food receipt. No sequence: a day's receipt is assembled from every row
     * logged that day, and keying it to one of their ids would change the
     * number depending on which row happened to answer the URL.
     */
    public static function forDay(?CarbonInterface $occurredAt): string
    {
        return $occurredAt === null ? '0000' : $occurredAt->format('Ymd');
    }
}
