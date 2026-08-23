<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * One-way conversion of HTML into the site's Portable Text dialect, for content
 * arriving from somewhere that only speaks HTML. Anything with no equivalent
 * node type is unwrapped to the text it contains rather than dropped.
 */
class HtmlToPortableText
{
    private const ROOT_ID = 'html-to-portable-text-root';

    /** Elements whose text is markup rather than content, so is not kept. */
    private const IGNORED = ['script', 'style', 'noscript', 'template', 'svg', 'head'];

    private const BOLD = ['strong', 'b'];

    private const ITALIC = ['em', 'i'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function convert(string $html): array
    {
        $root = self::parse($html);

        if ($root === null) {
            return [];
        }

        $nodes = [];
        self::walk($root, $nodes);

        return $nodes;
    }

    private static function parse(string $html): ?DOMElement
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="'.self::ROOT_ID.'">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        // getElementById needs a DTD to know which attribute is the id, so the
        // wrapper is found by position instead: it is the outermost div.
        $root = $document->getElementsByTagName('div')->item(0);

        return $root instanceof DOMElement && $root->getAttribute('id') === self::ROOT_ID ? $root : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function walk(DOMNode $parent, array &$nodes): void
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                self::appendTextBlock($child, $nodes);

                continue;
            }

            if ($child instanceof DOMElement) {
                self::element($child, $nodes);
            }
        }
    }

    /**
     * Loose text between block elements, which HTML allows and the dialect has
     * nowhere to put other than a paragraph of its own.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function appendTextBlock(DOMText $text, array &$nodes): void
    {
        $content = trim(self::normalise($text->textContent));

        if ($content !== '') {
            $nodes[] = PortableText::block($content);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function element(DOMElement $element, array &$nodes): void
    {
        $name = strtolower($element->nodeName);

        match (true) {
            $name === 'p' => self::appendBlock($element, $nodes, 'normal'),
            $name === 'blockquote' => self::appendBlock($element, $nodes, 'blockquote'),
            // h1 has no place inside a body: the entry title is the only one.
            in_array($name, ['h1', 'h2'], true) => self::appendBlock($element, $nodes, 'h2'),
            in_array($name, ['h3', 'h4', 'h5', 'h6'], true) => self::appendBlock($element, $nodes, $name),
            in_array($name, ['ul', 'ol'], true) => self::appendList($element, $nodes, 1),
            $name === 'figure' => self::appendFigure($element, $nodes),
            $name === 'img' => self::append(self::image($element, null), $nodes),
            $name === 'iframe' => self::append(self::video($element), $nodes),
            $name === 'pre' => self::append(self::code($element), $nodes),
            $name === 'hr' => self::append(['_type' => 'divider', '_key' => PortableText::key()], $nodes),
            in_array($name, self::IGNORED, true) => null,
            // Wrappers with no equivalent here (columns, embeds, tables) are
            // walked through, so their text survives even when their layout
            // does not.
            default => self::walk($element, $nodes),
        };
    }

    /**
     * @param  array<string, mixed>|null  $node
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function append(?array $node, array &$nodes): void
    {
        if ($node !== null) {
            $nodes[] = $node;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function appendBlock(DOMElement $element, array &$nodes, string $style): void
    {
        $block = self::richText($element, $style);

        if ($block !== null) {
            $nodes[] = $block;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function appendList(DOMElement $list, array &$nodes, int $level): void
    {
        $listItem = strtolower($list->nodeName) === 'ol' ? 'number' : 'bullet';

        foreach ($list->childNodes as $item) {
            if (! $item instanceof DOMElement || strtolower($item->nodeName) !== 'li') {
                continue;
            }

            $block = self::richText($item, 'normal');

            if ($block !== null) {
                $nodes[] = [...$block, 'listItem' => $listItem, 'level' => $level];
            }

            foreach ($item->childNodes as $nested) {
                if ($nested instanceof DOMElement && in_array(strtolower($nested->nodeName), ['ul', 'ol'], true)) {
                    self::appendList($nested, $nodes, $level + 1);
                }
            }
        }
    }

    /**
     * A figure is whatever it wraps: an image or an embed, with its caption.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function appendFigure(DOMElement $figure, array &$nodes): void
    {
        $caption = null;

        foreach ($figure->getElementsByTagName('figcaption') as $element) {
            $caption = trim(self::normalise($element->textContent));

            break;
        }

        $image = $figure->getElementsByTagName('img')->item(0);

        if ($image instanceof DOMElement) {
            self::append(self::image($image, $caption), $nodes);

            return;
        }

        $iframe = $figure->getElementsByTagName('iframe')->item(0);

        if ($iframe instanceof DOMElement) {
            self::append(self::video($iframe, $caption), $nodes);

            return;
        }

        self::walk($figure, $nodes);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function image(DOMElement $image, ?string $caption): ?array
    {
        $url = trim($image->getAttribute('src'));

        if ($url === '') {
            return null;
        }

        // Deliberately not falling back to alt: the dialect renders caption as
        // both the alt text and a visible figcaption, so borrowing alt would
        // print a caption the original never showed.
        return array_filter([
            '_type' => 'image',
            '_key' => PortableText::key(),
            'url' => $url,
            'caption' => $caption !== '' ? $caption : null,
            ...self::dimensions($image),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function video(DOMElement $embed, ?string $caption = null): ?array
    {
        $url = trim($embed->getAttribute('src'));
        $youtube = YouTube::id($url);

        if ($youtube !== null) {
            $url = "https://www.youtube.com/watch?v={$youtube}";
        }

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return array_filter([
            '_type' => 'video',
            '_key' => PortableText::key(),
            'url' => $url,
            'caption' => $caption !== '' ? $caption : null,
            ...self::dimensions($embed),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{width: ?int, height: ?int}
     */
    private static function dimensions(DOMElement $element): array
    {
        $width = (int) $element->getAttribute('width');
        $height = (int) $element->getAttribute('height');

        return $width > 0 && $height > 0
            ? ['width' => $width, 'height' => $height]
            : ['width' => null, 'height' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function code(DOMElement $pre): ?array
    {
        $code = rtrim($pre->textContent);

        return $code === '' ? null : ['_type' => 'code', '_key' => PortableText::key(), 'code' => $code];
    }

    /**
     * One block's worth of rich text, or null when it holds nothing to show.
     *
     * @return array<string, mixed>|null
     */
    private static function richText(DOMElement $element, string $style): ?array
    {
        $children = [];
        $markDefs = [];

        self::inline($element, $children, $markDefs);
        $children = self::trimEnds($children);

        if ($children === []) {
            return null;
        }

        // Editors habitually bold a whole heading, which the heading style
        // already does and which would otherwise render as a double weight.
        if (str_starts_with($style, 'h')) {
            $children = array_map(fn (array $span): array => [
                ...$span,
                'marks' => array_values(array_diff($span['marks'], ['strong'])),
            ], $children);
        }

        return [
            '_type' => 'block',
            '_key' => PortableText::key(),
            'style' => $style,
            'markDefs' => $markDefs,
            'children' => $children,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>  $markDefs
     * @param  list<string>  $marks
     */
    private static function inline(DOMNode $parent, array &$children, array &$markDefs, array $marks = []): void
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = self::normalise($child->textContent);

                if ($text !== '') {
                    $children[] = PortableText::span($text, $marks);
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $name = strtolower($child->nodeName);

            // A list inside a block is a block of its own, handled by appendList.
            if (in_array($name, ['ul', 'ol'], true)) {
                continue;
            }

            if ($name === 'br') {
                $children[] = PortableText::span("\n", $marks);

                continue;
            }

            self::inline($child, $children, $markDefs, self::marksFor($child, $name, $marks, $markDefs));
        }
    }

    /**
     * @param  list<string>  $marks
     * @param  array<int, array<string, mixed>>  $markDefs
     * @return list<string>
     */
    private static function marksFor(DOMElement $element, string $name, array $marks, array &$markDefs): array
    {
        if (in_array($name, self::BOLD, true)) {
            $marks[] = 'strong';
        } elseif (in_array($name, self::ITALIC, true)) {
            $marks[] = 'em';
        } elseif ($name === 'code') {
            $marks[] = 'code';
        } elseif ($name === 'a') {
            $href = trim($element->getAttribute('href'));

            // A relative or malformed href fails the dialect's link contract, so
            // the text survives unlinked rather than the block being rejected.
            if (filter_var($href, FILTER_VALIDATE_URL) !== false) {
                $key = PortableText::key();
                $markDefs[] = ['_key' => $key, '_type' => 'link', 'href' => $href];
                $marks[] = $key;
            }
        }

        return array_values(array_unique($marks));
    }

    /**
     * Drop the leading and trailing whitespace HTML indentation leaves behind,
     * along with any span left empty by doing so.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    private static function trimEnds(array $children): array
    {
        if ($children !== []) {
            $children[0]['text'] = ltrim($children[0]['text']);
            $last = array_key_last($children);
            $children[$last]['text'] = rtrim($children[$last]['text']);
        }

        return array_values(array_filter($children, fn (array $span): bool => $span['text'] !== ''));
    }

    /**
     * Collapse the runs of whitespace HTML treats as a single space, including
     * the non-breaking spaces editors sprinkle through pasted prose.
     */
    private static function normalise(string $text): string
    {
        return (string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text);
    }
}
