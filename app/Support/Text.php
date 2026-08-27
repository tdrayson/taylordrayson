<?php

namespace App\Support;

class Text
{
    /**
     * Truncate a string to a maximum length on a word boundary, trimming any
     * trailing whitespace, dashes or punctuation before appending the ellipsis
     * so it never reads like "… some topic -…".
     */
    /**
     * Join with commas and a final "and", so a run of values ends as prose
     * rather than as the last item of a list.
     *
     * @param  list<string>  $parts
     */
    public static function sentenceList(array $parts): string
    {
        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }

    public static function excerpt(?string $value, int $limit = 160, string $end = '…'): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $limit);

        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        $truncated = preg_replace('/[\s\p{P}]+$/u', '', $truncated);

        return $truncated.$end;
    }
}
