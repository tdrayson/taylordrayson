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
     * One-way conversion of a stored Editor.js document. Inline HTML is
     * stripped to plain spans (the only stored document is factory lorem).
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
