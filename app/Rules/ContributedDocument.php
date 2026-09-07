<?php

namespace App\Rules;

use App\Support\PortableText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Portable Text written by somebody who is not the author.
 *
 * An allowlist, and deliberately a much narrower one than the documents the
 * site itself publishes: a comment is a paragraph with emphasis and links, not
 * an article. Anything outside the list is rejected rather than stripped, since
 * the only client that can produce an invalid document here is one nobody
 * wrote, and silently rewriting somebody's words is worse than refusing them.
 *
 * The href check is the load-bearing one. A link mark is the single place a
 * stranger's document reaches an attribute rather than a text node, so
 * `javascript:` and `data:` are refused here rather than at render time.
 */
class ContributedDocument implements ValidationRule
{
    /** Emphasis a reply can carry. No headings, no lists, no blockquotes. */
    private const DECORATORS = ['strong', 'em', 'code'];

    /** The only annotation a contributed document may define. */
    private const ANNOTATIONS = ['link'];

    private const SCHEMES = ['http', 'https'];

    private const MAX_BLOCKS = 20;

    private const MAX_SPANS = 200;

    /** Shorter than this is a stray keystroke rather than a comment. */
    private const MIN_TEXT = 2;

    public function __construct(private int $max = 4000) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            $fail('That comment could not be read.');

            return;
        }

        if (count($value) > self::MAX_BLOCKS) {
            $fail('That comment is too long.');

            return;
        }

        $spans = 0;

        foreach ($value as $block) {
            if (! $this->blockIsAllowed($block, $spans)) {
                $fail('That comment contains formatting that is not allowed here.');

                return;
            }
        }

        $length = mb_strlen(trim(PortableText::plainText($value)));

        if ($length < self::MIN_TEXT) {
            $fail('Write something first.');

            return;
        }

        if ($length > $this->max) {
            $fail("That comment must not be longer than {$this->max} characters.");
        }
    }

    /**
     * @param  int  $spans  Running total across the document, so a hostile
     *                      payload cannot spread its spans over many blocks.
     */
    private function blockIsAllowed(mixed $block, int &$spans): bool
    {
        if (! is_array($block) || ($block['_type'] ?? null) !== 'block') {
            return false;
        }

        // A list item or a heading is a shape this renderer will not draw, so
        // accepting one would store something that can never be shown.
        if (($block['style'] ?? 'normal') !== 'normal' || isset($block['listItem'])) {
            return false;
        }

        $keys = [];

        foreach ($block['markDefs'] ?? [] as $def) {
            if (! $this->annotationIsAllowed($def)) {
                return false;
            }

            $keys[] = $def['_key'];
        }

        foreach ($block['children'] ?? [] as $child) {
            if (++$spans > self::MAX_SPANS || ! $this->spanIsAllowed($child, $keys)) {
                return false;
            }
        }

        return true;
    }

    private function annotationIsAllowed(mixed $def): bool
    {
        if (! is_array($def) || ! in_array($def['_type'] ?? null, self::ANNOTATIONS, true)) {
            return false;
        }

        if (! is_string($def['_key'] ?? null) || ! is_string($def['href'] ?? null)) {
            return false;
        }

        // Parsed rather than pattern-matched, and compared lowercase, so
        // `JavaScript:` and a scheme padded with control characters are the
        // same answer as the plain form.
        $scheme = parse_url(trim($def['href']), PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::SCHEMES, true);
    }

    /**
     * @param  list<string>  $keys  Annotation keys defined by the owning block.
     */
    private function spanIsAllowed(mixed $span, array $keys): bool
    {
        if (! is_array($span) || ($span['_type'] ?? 'span') !== 'span' || ! is_string($span['text'] ?? null)) {
            return false;
        }

        foreach ($span['marks'] ?? [] as $mark) {
            // A mark is either emphasis or a pointer at one of this block's own
            // annotations. A key naming nothing would render as unmarked text,
            // so it is a malformed document rather than a harmless one.
            if (! is_string($mark) || (! in_array($mark, self::DECORATORS, true) && ! in_array($mark, $keys, true))) {
                return false;
            }
        }

        return true;
    }
}
