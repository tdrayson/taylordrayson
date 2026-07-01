<?php

namespace App\Support;

class EditorJsToBard
{
    /**
     * Convert an Editor.js document array into a Bard (ProseMirror) node array
     * suitable for storage in a Statamic Bard field.
     *
     * @param  array<string, mixed>  $editorJs
     * @return array<int, array<string, mixed>>
     */
    public static function convert(array $editorJs): array
    {
        $blocks = $editorJs['blocks'] ?? [];

        if (! is_array($blocks)) {
            return [];
        }

        $nodes = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $data = $block['data'] ?? [];
            $node = match ($type) {
                'paragraph' => self::paragraph($data),
                'header' => self::heading($data),
                'list' => self::list($data),
                'quote' => self::blockquote($data),
                'image' => self::image($data),
                default => self::unknown($type, $data),
            };

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /** @param array<string, mixed> $data */
    private static function paragraph(array $data): array
    {
        $html = $data['text'] ?? '';

        return [
            'type' => 'paragraph',
            'content' => self::inlineContent($html),
        ];
    }

    /** @param array<string, mixed> $data */
    private static function heading(array $data): array
    {
        $html = $data['text'] ?? '';
        $level = (int) ($data['level'] ?? 2);

        return [
            'type' => 'heading',
            'attrs' => ['level' => $level],
            'content' => self::inlineContent($html),
        ];
    }

    /** @param array<string, mixed> $data */
    private static function list(array $data): array
    {
        $style = $data['style'] ?? 'unordered';
        $items = $data['items'] ?? [];
        $listType = $style === 'ordered' ? 'orderedList' : 'bulletList';

        $listItems = array_map(function ($item) {
            $text = is_array($item) ? ($item['content'] ?? '') : (string) $item;

            return [
                'type' => 'listItem',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => self::inlineContent($text),
                    ],
                ],
            ];
        }, $items);

        return [
            'type' => $listType,
            'content' => $listItems,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function blockquote(array $data): array
    {
        $html = $data['text'] ?? '';

        return [
            'type' => 'blockquote',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => self::inlineContent($html),
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private static function image(array $data): array
    {
        $url = $data['file']['url'] ?? $data['url'] ?? '';
        $caption = $data['caption'] ?? '';

        return [
            'type' => 'image',
            'attrs' => [
                'src' => $url,
                'alt' => $caption,
                'title' => $caption ?: null,
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private static function unknown(string $type, array $data): ?array
    {
        rescue(fn () => logger()?->warning("EditorJsToBard: unknown block type '{$type}', falling back to paragraph."), report: false);

        $text = $data['text'] ?? (isset($data['code']) ? $data['code'] : '');

        return [
            'type' => 'paragraph',
            'content' => self::inlineContent((string) $text),
        ];
    }

    /**
     * Convert an HTML string containing inline markup (bold, links, etc.) into
     * a ProseMirror inline content array (text nodes with marks).
     *
     * For simplicity, we emit a single text node with the HTML stripped,
     * preserving the plain text so the content is never lost.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function inlineContent(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($text === '') {
            return [];
        }

        return [
            ['type' => 'text', 'text' => $text],
        ];
    }
}
