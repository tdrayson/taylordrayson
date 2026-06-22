<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

/**
 * Allowlist HTML sanitizer. Anything outside the allowed tag/attribute map is
 * unwrapped to its plain text, and links with unsafe URL schemes are dropped.
 * Text content and line breaks are preserved.
 */
class HtmlSanitizer
{
    private const ROOT_ID = 'html-sanitizer-root';

    /** @var list<string> */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * @param  array<string, list<string>>  $allowed  Tag name => permitted attributes.
     */
    public static function clean(string $html, array $allowed = ['a' => ['href']]): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="'.self::ROOT_ID.'">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            if ($element === $root) {
                continue;
            }

            self::sanitizeElement($element, $allowed);
        }

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    /**
     * @param  array<string, list<string>>  $allowed
     */
    private static function sanitizeElement(DOMElement $element, array $allowed): void
    {
        $tag = strtolower($element->tagName);

        if (! isset($allowed[$tag])) {
            self::unwrap($element);

            return;
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array($attribute->name, $allowed[$tag], true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a' && ! self::hasSafeHref($element->getAttribute('href'))) {
            self::unwrap($element);
        }
    }

    private static function hasSafeHref(string $href): bool
    {
        if (trim($href) === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return $scheme === '' || in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    /**
     * Replace an element with its own text content, dropping the tag.
     */
    private static function unwrap(DOMElement $element): void
    {
        $element->parentNode?->replaceChild(
            $element->ownerDocument->createTextNode($element->textContent),
            $element,
        );
    }
}
