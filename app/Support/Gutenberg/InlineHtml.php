<?php

namespace App\Support\Gutenberg;

use App\Support\PortableText;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Convert the inline markup inside one block into Portable Text spans.
 *
 * Only the tags the source content actually uses are handled, which across all
 * 127 posts is code, a, strong, em, br, img, span and sup. Anything else is
 * transparent: its text survives and its tag does not.
 */
final class InlineHtml
{
    private const DECORATORS = [
        'strong' => 'strong',
        'b' => 'strong',
        'em' => 'em',
        'i' => 'em',
        'code' => 'code',
    ];

    /** @var list<array<string, mixed>> */
    private array $children = [];

    /** @var list<array<string, mixed>> */
    private array $markDefs = [];

    /** @var list<array<string, mixed>> */
    private array $images = [];

    /**
     * Spans, the link definitions they reference, and any images lifted out.
     *
     * An image cannot sit inside a text block in this dialect, so one written
     * inside a paragraph comes back separately for the caller to place as its
     * own sibling node.
     *
     * @return array{children: list<array<string, mixed>>, markDefs: list<array<string, mixed>>, images: list<array<string, mixed>>}
     */
    public function convert(string $html): array
    {
        $this->children = [];
        $this->markDefs = [];
        $this->images = [];

        $document = $this->document($html);

        if ($document !== null) {
            foreach ($document->childNodes as $node) {
                $this->walk($node, []);
            }
        }

        return [
            'children' => $this->merged(),
            'markDefs' => $this->markDefs,
            'images' => $this->images,
        ];
    }

    /**
     * @param  list<string>  $marks
     */
    private function walk(DOMNode $node, array $marks): void
    {
        if ($node instanceof DOMText) {
            $this->text($node->textContent, $marks);

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->nodeName);

        if ($tag === 'br') {
            // The dialect has no soft break, so the line ending stays inside
            // the span, matching PortableText::fromPlainText().
            $this->text("\n", $marks);

            return;
        }

        if ($tag === 'img') {
            $src = $node->getAttribute('src');

            if ($src !== '') {
                $this->images[] = array_filter([
                    '_type' => 'image',
                    '_key' => PortableText::key(),
                    'url' => Url::normalise($src),
                    'alt' => $node->getAttribute('alt') ?: null,
                ], fn (mixed $value): bool => $value !== null);
            }

            return;
        }

        if ($tag === 'a' && $node->getAttribute('href') !== '') {
            $key = PortableText::key();
            $this->markDefs[] = ['_key' => $key, '_type' => 'link', 'href' => $node->getAttribute('href')];
            $marks = [...$marks, $key];
        } elseif (isset(self::DECORATORS[$tag])) {
            $marks = [...$marks, self::DECORATORS[$tag]];
        }

        foreach ($node->childNodes as $child) {
            $this->walk($child, $marks);
        }
    }

    /**
     * @param  list<string>  $marks
     */
    private function text(string $text, array $marks): void
    {
        // A non-breaking space is a space here: it exists in the source because
        // the editor inserted it, not because anything depends on it.
        $text = str_replace("\u{00A0}", ' ', $text);

        if ($text === '') {
            return;
        }

        $this->children[] = PortableText::span($text, array_values(array_unique($marks)));
    }

    /**
     * Runs of identically marked text become one span, since the walk emits a
     * span per text node and nested tags split what is really one run.
     *
     * @return list<array<string, mixed>>
     */
    private function merged(): array
    {
        $out = [];

        foreach ($this->children as $span) {
            $last = $out[count($out) - 1] ?? null;

            if ($last !== null && $last['marks'] === $span['marks']) {
                $out[count($out) - 1]['text'] .= $span['text'];

                continue;
            }

            $out[] = $span;
        }

        return array_values(array_filter($out, fn (array $span): bool => $span['text'] !== ''));
    }

    /** The fragment's own node list, or null when it will not parse. */
    private function document(string $html): ?DOMNode
    {
        if (trim($html) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? ($document->getElementsByTagName('body')->item(0) ?? null) : null;
    }
}
