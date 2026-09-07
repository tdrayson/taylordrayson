<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * The HTML inside a webmention's e-content, as Portable Text.
 *
 * We used to take mf2's plain-text rendering of the same content, which threw
 * away every link, quote and list a reply contained. This keeps them, without
 * ever holding the sender's HTML: an element with no case below contributes its
 * text and nothing else, so the output is built from an allowlist rather than
 * filtered by a blocklist. There is no tag to forget.
 *
 * The mf2 properties themselves are untouched by this. Author, published date
 * and the kind of response live in their own columns, read from the microformat
 * before this ever runs.
 */
final class HtmlToPortableText
{
    /** Block elements that keep their own shape. Everything else is unwrapped. */
    private const BLOCKS = [
        'p' => 'normal',
        'blockquote' => 'blockquote',
        'h1' => 'normal',
        'h2' => 'normal',
        'h3' => 'normal',
        'h4' => 'normal',
        'h5' => 'normal',
        'h6' => 'normal',
        'pre' => 'normal',
        'div' => 'normal',
    ];

    private const DECORATORS = [
        'strong' => 'strong',
        'b' => 'strong',
        'em' => 'em',
        'i' => 'em',
        'code' => 'code',
    ];

    /** An image contributes its description or nothing; long alt is a caption. */
    private const MAX_LABEL = 120;

    /**
     * Elements whose text is not prose. Unwrapping these would harvest the
     * contents of a script tag into the response as readable words.
     */
    private const SKIP = ['script', 'style', 'noscript', 'template', 'iframe', 'object', 'svg', 'head'];

    /** Long enough for a real response, short of a document sent as one. */
    private const MAX_BLOCKS = 60;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function convert(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return [];
        }

        $root = $document->getElementsByTagName('div')->item(0);

        return $root === null ? [] : array_slice(self::blocksIn($root), 0, self::MAX_BLOCKS);
    }

    /**
     * Blocks from a container's children, with any loose inline text gathered
     * into a paragraph of its own.
     *
     * @return list<array<string, mixed>>
     */
    private static function blocksIn(DOMNode $container): array
    {
        $blocks = [];
        $loose = [];

        $flush = function () use (&$blocks, &$loose): void {
            if ($loose !== []) {
                $blocks[] = self::block($loose, 'normal');
                $loose = [];
            }
        };

        foreach ($container->childNodes as $child) {
            $name = $child instanceof DOMElement ? strtolower($child->nodeName) : null;

            if ($name !== null && in_array($name, self::SKIP, true)) {
                continue;
            }

            if ($name === 'ul' || $name === 'ol') {
                $flush();
                $blocks = [...$blocks, ...self::listBlocks($child, $name === 'ol' ? 'number' : 'bullet')];

                continue;
            }

            if ($name !== null && isset(self::BLOCKS[$name])) {
                $flush();

                // A blockquote holds paragraphs of its own, so its children are
                // read as blocks and each one carries the quote's style.
                $inner = self::blocksIn($child);

                foreach ($inner as $block) {
                    $block['style'] = self::BLOCKS[$name] === 'blockquote' ? 'blockquote' : $block['style'];
                    $blocks[] = $block;
                }

                continue;
            }

            $loose = [...$loose, ...self::spansIn($child, [])];
        }

        $flush();

        return array_values(array_filter($blocks, fn (array $block): bool => self::hasText($block)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listBlocks(DOMNode $list, string $listItem): array
    {
        $blocks = [];

        foreach ($list->childNodes as $item) {
            if (! $item instanceof DOMElement || strtolower($item->nodeName) !== 'li') {
                continue;
            }

            $block = self::block(self::spansIn($item, []), 'normal');
            $block['listItem'] = $listItem;
            $block['level'] = 1;
            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Spans for a node's text, carrying whichever marks are open above it.
     *
     * @param  list<string>  $marks
     * @return list<array<string, mixed>>
     */
    private static function spansIn(DOMNode $node, array $marks): array
    {
        if ($node instanceof DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->textContent) ?? '';

            return $text === '' ? [] : [PortableText::span($text, $marks)];
        }

        if (! $node instanceof DOMElement) {
            return [];
        }

        $name = strtolower($node->nodeName);

        if (in_array($name, self::SKIP, true)) {
            return [];
        }

        if ($name === 'br') {
            return [PortableText::span("\n", $marks)];
        }

        // No <img> reaches the page, so its description is the only thing of
        // it worth keeping. Empty alt yields no span at all, which is what
        // stops a linked image becoming an anchor around nothing.
        if ($name === 'img') {
            $label = self::label($node, ['alt', 'title']);

            return $label === '' ? [] : [PortableText::span($label, $marks)];
        }

        // Carried on the span rather than resolved here: the block that owns
        // these spans is the thing that holds markDefs.
        if ($name === 'a') {
            $href = trim($node->getAttribute('href'));

            if (self::isFetchableLink($href)) {
                $marks = [...$marks, 'href:'.$href];
            }
        }

        if (isset(self::DECORATORS[$name])) {
            $marks = [...$marks, self::DECORATORS[$name]];
        }

        $spans = [];

        foreach ($node->childNodes as $child) {
            $spans = [...$spans, ...self::spansIn($child, $marks)];
        }

        // A link whose only content was an undescribed image would otherwise be
        // dropped silently; its own labelling attributes still name where it goes.
        if ($name === 'a' && $spans === []) {
            $label = self::label($node, ['title', 'aria-label']);

            return $label === '' ? [] : [PortableText::span($label, $marks)];
        }

        return $spans;
    }

    /**
     * The first of these attributes that says something, collapsed and capped.
     *
     * @param  list<string>  $attributes
     */
    private static function label(DOMElement $node, array $attributes): string
    {
        foreach ($attributes as $attribute) {
            $value = trim(preg_replace('/\s+/u', ' ', $node->getAttribute($attribute)) ?? '');

            if ($value !== '') {
                return mb_substr($value, 0, self::MAX_LABEL);
            }
        }

        return '';
    }

    /**
     * Turn the spans' `href:` placeholders into real annotations, which is the
     * only point at which a block knows all the links it contains.
     *
     * @param  list<array<string, mixed>>  $spans
     * @return array<string, mixed>
     */
    private static function block(array $spans, string $style): array
    {
        $markDefs = [];
        $keyFor = [];

        foreach ($spans as $index => $span) {
            $marks = [];

            foreach ($span['marks'] as $mark) {
                if (! str_starts_with($mark, 'href:')) {
                    $marks[] = $mark;

                    continue;
                }

                $href = substr($mark, 5);

                if (! isset($keyFor[$href])) {
                    $keyFor[$href] = PortableText::key();
                    $markDefs[] = ['_type' => 'link', '_key' => $keyFor[$href], 'href' => $href];
                }

                $marks[] = $keyFor[$href];
            }

            $spans[$index]['marks'] = $marks;
        }

        return [
            '_type' => 'block',
            '_key' => PortableText::key(),
            'style' => $style,
            'markDefs' => $markDefs,
            'children' => array_values($spans),
        ];
    }

    /** @param  array<string, mixed>  $block */
    private static function hasText(array $block): bool
    {
        foreach ($block['children'] as $span) {
            if (trim($span['text']) !== '') {
                return true;
            }
        }

        return false;
    }

    /** http(s) only: a link nobody can follow is text, and the rest are traps. */
    private static function isFetchableLink(string $href): bool
    {
        $scheme = parse_url($href, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }
}
