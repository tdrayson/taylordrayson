<?php

namespace App\Actions\Snippetclub;

/**
 * Decide whether an old grey preformatted box was a note or a code fragment.
 *
 * The source site had no callout of its own, so a `<pre>` did both jobs: an
 * aside to the reader, and a shortcode or file path shown inline. Nothing in
 * the markup separates them, so this reads the text.
 *
 * One-off, for the SnippetClub migration. Delete it once the content has moved.
 */
final class ClassifyPreformatted
{
    /** Words that turn an advisory note into a stronger one. */
    private const CAUTIONS = ['shouldn\'t', 'should not', 'never', 'be careful', 'careful', 'warning', 'cannot', 'only as'];

    private const IMPORTANT = ['note:', 'updated ', 'requires', 'must '];

    /**
     * The callout variant this text wants, or null when it is code.
     *
     * @return 'note'|'tip'|'important'|'warning'|'caution'|null
     */
    public function __invoke(string $text): ?string
    {
        $trimmed = trim($text);
        $lower = mb_strtolower($trimmed);

        // Prose unless it looks like code. The boxes are overwhelmingly the
        // author talking to the reader, so an allow-list of openings would
        // misread every sentence that starts with a product name.
        if ($trimmed === '' || $this->looksLikeCode($trimmed) || str_word_count($trimmed) < 5) {
            return null;
        }

        foreach (self::CAUTIONS as $needle) {
            if (str_contains($lower, $needle)) {
                return 'warning';
            }
        }

        foreach (self::IMPORTANT as $needle) {
            if (str_starts_with($lower, $needle) || str_contains($lower, $needle)) {
                return 'important';
            }
        }

        return 'note';
    }

    /**
     * A shortcode, a path, a URL, a class reference or a run of data. None of
     * these are sentences however many words they happen to contain.
     */
    private function looksLikeCode(string $text): bool
    {
        // Prose has spaces in it. An encoded polyline runs to 857 characters
        // without one, and str_word_count still calls that 166 words, so the
        // word count alone lets it through.
        if (mb_strlen($text) >= 40 && preg_match_all('/\s/', $text) < mb_strlen($text) / 25) {
            return true;
        }

        return (bool) preg_match('#^\[[^\]]+\]#', $text)          // [shortcode ...]
            || (bool) preg_match('#^/[\w./-]+$#', $text)           // /a/path/to/file.php
            || (bool) preg_match('#^https?://#i', $text)           // a bare URL
            || (bool) preg_match('#^[A-Za-z]+\\\\[A-Za-z\\\\]+#', $text) // Namespaced\Class
            || (bool) preg_match('#^[A-Za-z_]+\s*=\s*\S#', $text)  // Name = value
            || (bool) preg_match('#^[A-Za-z0-9@?{}\[\]~^`|_-]{40,}$#', $text); // encoded data
    }
}
