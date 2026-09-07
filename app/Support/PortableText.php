<?php

namespace App\Support;

use Illuminate\Support\Str;

class PortableText
{
    /**
     * Flatten a Portable Text document into readable plain text for card
     * excerpts and previews. Only text blocks and code nodes carry text.
     *
     * @param  array<int, array<string, mixed>>|string|null  $document
     */
    public static function plainText(array|string|null $document): string
    {
        $parts = [];

        foreach (self::nodes($document) as $node) {
            if (($node['_type'] ?? null) === 'block') {
                $parts[] = implode('', array_map(
                    fn (array $child): string => $child['text'] ?? '',
                    $node['children'] ?? [],
                ));
            }

            if (($node['_type'] ?? null) === 'code') {
                $parts[] = $node['code'] ?? '';
            }
        }

        return trim((string) preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    /**
     * The first $max characters of readable text, cut at a block boundary where
     * one falls close enough and mid-span otherwise.
     *
     * Measured on the words rather than the encoded document, so marking a
     * phrase as a link cannot change where the cut lands.
     *
     * @param  array<int, array<string, mixed>>  $document
     * @return array<int, array<string, mixed>>
     */
    public static function truncate(array $document, int $max, string $ellipsis = '…'): array
    {
        if (mb_strlen(self::plainText($document)) <= $max) {
            return $document;
        }

        $kept = [];
        $used = 0;

        foreach ($document as $block) {
            $length = mb_strlen(self::plainText([$block]));

            if ($used + $length <= $max) {
                $kept[] = $block;
                // plainText joins blocks with a space, so the budget loses one.
                $used += $length + 1;

                continue;
            }

            $remaining = $max - $used;

            if ($remaining > 0) {
                $kept[] = self::trimBlock($block, $remaining, $ellipsis);
            } elseif ($kept !== []) {
                $last = array_key_last($kept);
                $kept[$last] = self::trimBlock($kept[$last], PHP_INT_MAX, $ellipsis);
            }

            break;
        }

        return array_values($kept);
    }

    /**
     * One block cut to $max characters of its own text, keeping the spans that
     * fit whole and cutting the one that straddles the limit.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private static function trimBlock(array $block, int $max, string $ellipsis): array
    {
        $children = [];
        $used = 0;

        foreach ($block['children'] ?? [] as $span) {
            $text = $span['text'] ?? '';
            $length = mb_strlen($text);

            if ($used + $length <= $max) {
                $children[] = $span;
                $used += $length;

                continue;
            }

            $remaining = $max - $used;

            if ($remaining > 0) {
                // Cut on the last space inside the budget, so the excerpt does
                // not end halfway through a word.
                $cut = mb_substr($text, 0, $remaining);
                $space = mb_strrpos($cut, ' ');
                $span['text'] = rtrim($space === false ? $cut : mb_substr($cut, 0, $space));
                $children[] = $span;
            }

            break;
        }

        if ($children !== []) {
            $last = array_key_last($children);
            $children[$last]['text'] = rtrim($children[$last]['text'], ' .,;:').$ellipsis;
        }

        $block['children'] = array_values($children);

        return $block;
    }

    /**
     * The document's text with its paragraph breaks intact, unlike
     * {@see plainText()} which collapses all whitespace.
     *
     * @param  array<int, array<string, mixed>>|string|null  $document
     */
    public static function text(array|string|null $document): string
    {
        $parts = [];

        foreach (self::nodes($document) as $node) {
            if (($node['_type'] ?? null) === 'block') {
                $parts[] = implode('', array_map(
                    fn (array $child): string => $child['text'] ?? '',
                    $node['children'] ?? [],
                ));
            }

            if (($node['_type'] ?? null) === 'code') {
                $parts[] = $node['code'] ?? '';
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Normalise a stored document (array or raw JSON) to the bare node list.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function nodes(array|string|null $document): array
    {
        if (is_string($document)) {
            $document = json_decode($document, true);
        }

        return is_array($document) && array_is_list($document) ? $document : [];
    }

    /**
     * Build a simple text block, used by factories and conversions.
     */
    public static function block(string $text, string $style = 'normal', ?string $listItem = null, int $level = 1): array
    {
        $block = [
            '_type' => 'block',
            '_key' => self::key(),
            'style' => $style,
            'markDefs' => [],
            'children' => [self::span($text)],
        ];

        if ($listItem !== null) {
            $block['listItem'] = $listItem;
            $block['level'] = $level;
        }

        return $block;
    }

    /**
     * Convert plain text to Portable Text, so a client that can only send a
     * string (Shortcuts, Micropub, a CSV import) still produces valid content.
     * Blank lines separate blocks; a single newline stays inside its block, the
     * dialect having no soft-break node.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fromPlainText(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $paragraphs = preg_split('/\R\s*\R/', trim($text)) ?: [];

        return array_values(array_map(
            fn (string $paragraph): array => self::autolinked(trim($paragraph)),
            array_filter($paragraphs, fn (string $paragraph): bool => trim($paragraph) !== ''),
        ));
    }

    /**
     * A paragraph with bare URLs turned into links. The editor does this itself
     * (Tiptap autolinks as you type), so this is for the clients that can only
     * send a string and would otherwise leave URLs as dead text.
     *
     * @return array<string, mixed>
     */
    private static function autolinked(string $paragraph): array
    {
        preg_match_all('#\bhttps?://[^\s<>"\']+#i', $paragraph, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return self::block($paragraph);
        }

        $children = [];
        $markDefs = [];
        $cursor = 0;

        foreach ($matches[0] as [$match, $offset]) {
            $url = self::withoutTrailingPunctuation($match);

            if ($offset > $cursor) {
                $children[] = self::span(substr($paragraph, $cursor, $offset - $cursor));
            }

            $key = self::key();
            $markDefs[] = ['_key' => $key, '_type' => 'link', 'href' => $url];
            $children[] = self::span($url, [$key]);

            $cursor = $offset + strlen($url);
        }

        if ($cursor < strlen($paragraph)) {
            $children[] = self::span(substr($paragraph, $cursor));
        }

        return [
            '_type' => 'block',
            '_key' => self::key(),
            'style' => 'normal',
            'markDefs' => $markDefs,
            'children' => $children,
        ];
    }

    /**
     * Trim sentence punctuation that the URL pattern greedily swallowed, and any
     * closing bracket with no opener, so "(see https://example.com/a)" links the
     * address rather than the address plus the bracket.
     */
    private static function withoutTrailingPunctuation(string $url): string
    {
        while ($url !== '' && str_contains('.,;:!?', substr($url, -1))) {
            $url = substr($url, 0, -1);
        }

        while (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) {
            $url = substr($url, 0, -1);
        }

        return $url;
    }

    /**
     * @param  list<string>  $marks
     */
    public static function span(string $text, array $marks = []): array
    {
        return ['_type' => 'span', '_key' => self::key(), 'text' => $text, 'marks' => $marks];
    }

    public static function key(): string
    {
        return Str::lower(Str::random(12));
    }

    /**
     * One-way conversion of a stored Editor.js document, stripping inline HTML
     * to plain spans.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fromEditorJs(array|string|null $document): array
    {
        if (is_string($document)) {
            $document = json_decode($document, true);
        }

        $blocks = is_array($document) ? ($document['blocks'] ?? []) : [];
        $nodes = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            $nodes = [...$nodes, ...match ($type) {
                'paragraph' => [self::block(strip_tags($data['text'] ?? ''))],
                'header' => [self::block(strip_tags($data['text'] ?? ''), ((int) ($data['level'] ?? 2)) <= 2 ? 'h2' : 'h3')],
                'quote' => [self::block(strip_tags(trim(($data['text'] ?? '').' '.($data['caption'] ?? ''))), 'blockquote')],
                'list', 'nestedlist' => self::listBlocks($data),
                'code' => [['_type' => 'code', '_key' => self::key(), 'code' => $data['code'] ?? '']],
                'delimiter' => [['_type' => 'divider', '_key' => self::key()]],
                'image' => array_filter([self::imageNode($data)]),
                default => [],
            }];
        }

        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function listBlocks(array $data, int $level = 1): array
    {
        $listItem = ($data['style'] ?? 'unordered') === 'ordered' ? 'number' : 'bullet';
        $nodes = [];

        foreach ($data['items'] ?? [] as $item) {
            $text = is_array($item) ? ($item['content'] ?? '') : $item;
            $nodes[] = self::block(strip_tags($text), 'normal', $listItem, $level);

            if (is_array($item) && ! empty($item['items'])) {
                $nodes = [...$nodes, ...self::listBlocks(['style' => $data['style'] ?? 'unordered', 'items' => $item['items']], $level + 1)];
            }
        }

        return $nodes;
    }

    private static function imageNode(array $data): ?array
    {
        $url = $data['file']['url'] ?? $data['url'] ?? null;

        if (! $url) {
            return null;
        }

        return array_filter([
            '_type' => 'image',
            '_key' => self::key(),
            'url' => $url,
            'caption' => isset($data['caption']) ? strip_tags($data['caption']) : null,
        ], fn ($value) => $value !== null);
    }
}
