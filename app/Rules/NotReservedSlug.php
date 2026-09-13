<?php

namespace App\Rules;

use App\Support\TimelineUrlSlug;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Fails when a slug is one of the words reserved for a dataset's day URL
 * (e.g. "food", "sleep"), so a hand-written entry cannot collide with it.
 */
class NotReservedSlug implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && TimelineUrlSlug::isReserved($value)) {
            $fail(TimelineUrlSlug::reservationMessage($value));
        }
    }
}
