<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Either Portable Text blocks or a plain string. The editor posts blocks; every
 * other way an entry arrives (Micropub, a Shortcut, an import) has only a
 * string to give, and the model wraps one into a single block on the way in.
 */
class TextOrDocument implements ValidationRule
{
    private const MAX_LENGTH = 5000;

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value)) {
            return;
        }

        if (! is_string($value)) {
            $fail("The {$attribute} must be text or a document.");

            return;
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            $fail("The {$attribute} must not be longer than ".self::MAX_LENGTH.' characters.');
        }
    }
}
