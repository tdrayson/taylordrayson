<?php

namespace App\Queries;

use App\Support\Period;

/**
 * Free text like "last tuesday" or "1 march" resolved to an absolute date,
 * through the exact grammar {@see Period::parse()} applies to a tag's `from`/
 * `to` bound at render time. One grammar, not a client-side parser that could
 * drift from it.
 */
final class ResolveFreeTextDate
{
    /** Null when `$value` is not a date PHP's own parser recognises. */
    public function __invoke(string $value): ?string
    {
        return Period::parse($value)?->toDateString();
    }
}
