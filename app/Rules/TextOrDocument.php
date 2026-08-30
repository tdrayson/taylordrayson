<?php

namespace App\Rules;

use App\Support\PortableText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Either Portable Text blocks or a plain string, within a length.
 *
 * The editor posts blocks; every other way an entry arrives (Micropub, a
 * Shortcut, an import) has only a string to give, and the model wraps one into
 * a single block on the way in. Both are measured on readable text, so marking
 * a word as a link cannot change the count.
 */
class TextOrDocument implements ValidationRule
{
    /** What a field without its own declared limit is held to. */
    private const DEFAULT_MAX = 5000;

    public function __construct(private ?int $max = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) && ! is_string($value)) {
            $fail("The {$attribute} must be text or a document.");

            return;
        }

        // A plain string is already its own readable text; PortableText::plainText
        // would read it as a document, fail to decode it and return nothing.
        $text = is_array($value) ? PortableText::plainText($value) : $value;

        $max = $this->max ?? self::DEFAULT_MAX;

        if (mb_strlen($text) > $max) {
            $fail("The {$attribute} must not be longer than {$max} characters.");
        }
    }
}
