<?php

namespace App\Support;

class EditorJs
{
    /**
     * Flatten an Editor.js document into readable plain text, used for card
     * titles and previews. Inline HTML (bold, links) is stripped.
     *
     * @param  array<string, mixed>|string|null  $document
     */
    public static function plainText(array|string|null $document): string
    {
        $blocks = self::blocks($document);
        $parts = [];

        foreach ($blocks as $block) {
            $data = $block['data'] ?? [];

            if (isset($data['text'])) {
                $parts[] = $data['text'];
            }

            if (isset($data['code'])) {
                $parts[] = $data['code'];
            }

            foreach ($data['items'] ?? [] as $item) {
                $parts[] = is_array($item) ? ($item['content'] ?? '') : $item;
            }
        }

        $text = strip_tags(implode(' ', $parts));

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Wrap blocks in a minimal Editor.js document envelope.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{blocks: array<int, array<string, mixed>>, version: string}
     */
    public static function document(array $blocks): array
    {
        return ['blocks' => $blocks, 'version' => '2.30.0'];
    }

    /**
     * Safely pull the block list out of a document (decoding a JSON string when
     * needed), returning an empty list for anything malformed.
     *
     * @param  array<string, mixed>|string|null  $document
     * @return array<int, array<string, mixed>>
     */
    private static function blocks(array|string|null $document): array
    {
        $decoded = is_string($document) ? json_decode($document, true) : $document;

        return is_array($decoded) && is_array($decoded['blocks'] ?? null) ? $decoded['blocks'] : [];
    }
}
