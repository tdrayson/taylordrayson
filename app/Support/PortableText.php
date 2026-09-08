<?php

namespace App\Support;

use App\DynamicTags\DynamicTagRegistry;
use App\Rules\ValidPortableText;
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
     * The document as markdown: headings, lists, links, emphasis, images and
     * fenced code. Covers exactly the node types the body components render.
     *
     * @param  array<int, array<string, mixed>>|string|null  $document
     */
    public static function markdown(array|string|null $document): string
    {
        $out = [];

        foreach (self::nodes($document) as $node) {
            $out[] = match ($node['_type'] ?? null) {
                'block' => self::markdownBlock($node),
                'image' => '!['.($node['alt'] ?? '').']('.($node['url'] ?? '').')',
                'video' => '['.($node['title'] ?? 'Video').']('.($node['url'] ?? '').')',
                'code' => "```\n".($node['code'] ?? '')."\n```",
                'divider' => '---',
                default => null,
            };
        }

        return trim(implode("\n\n", array_filter($out, fn (?string $part): bool => $part !== null && $part !== '')));
    }

    /**
     * The document as HTML, for the e-content a microformats parser reads.
     *
     * @param  array<int, array<string, mixed>>|string|null  $document
     */
    public static function html(array|string|null $document): string
    {
        $out = [];
        $openList = null;

        foreach (self::nodes($document) as $node) {
            $listItem = ($node['_type'] ?? null) === 'block' ? ($node['listItem'] ?? null) : null;

            // Consecutive items of one kind are one list; anything else closes it.
            if ($openList !== null && $listItem !== $openList) {
                $out[] = $openList === 'number' ? '</ol>' : '</ul>';
                $openList = null;
            }

            if ($listItem !== null && $openList === null) {
                $out[] = $listItem === 'number' ? '<ol>' : '<ul>';
                $openList = $listItem;
            }

            $out[] = match ($node['_type'] ?? null) {
                'block' => self::htmlBlock($node),
                'image' => '<img src="'.e($node['url'] ?? '').'" alt="'.e($node['alt'] ?? '').'">',
                'video' => '<a href="'.e($node['url'] ?? '').'">'.e($node['title'] ?? 'Video').'</a>',
                'code' => '<pre><code>'.e($node['code'] ?? '').'</code></pre>',
                'divider' => '<hr>',
                default => null,
            };
        }

        if ($openList !== null) {
            $out[] = $openList === 'number' ? '</ol>' : '</ul>';
        }

        return implode('', array_filter($out, fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function markdownBlock(array $node): string
    {
        $text = self::inline($node, markdown: true);

        if (($node['listItem'] ?? null) !== null) {
            $indent = str_repeat('  ', max(0, (int) ($node['level'] ?? 1) - 1));

            return $indent.($node['listItem'] === 'number' ? '1. ' : '- ').$text;
        }

        return match ($node['style'] ?? 'normal') {
            'h2' => "## {$text}",
            'h3' => "### {$text}",
            'h4' => "#### {$text}",
            'blockquote' => "> {$text}",
            default => $text,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function htmlBlock(array $node): string
    {
        $text = self::inline($node, markdown: false);

        if (($node['listItem'] ?? null) !== null) {
            return "<li>{$text}</li>";
        }

        return match ($node['style'] ?? 'normal') {
            'h2' => "<h2>{$text}</h2>",
            'h3' => "<h3>{$text}</h3>",
            'h4' => "<h4>{$text}</h4>",
            'blockquote' => "<blockquote>{$text}</blockquote>",
            default => "<p>{$text}</p>",
        };
    }

    /**
     * A block's spans with their marks applied. A mark that is not `strong` or
     * `em` is a markDef key, which is how Portable Text carries a link.
     *
     * @param  array<string, mixed>  $node
     */
    private static function inline(array $node, bool $markdown): string
    {
        $hrefs = [];

        foreach ($node['markDefs'] ?? [] as $def) {
            if (($def['_type'] ?? null) === 'link') {
                $hrefs[$def['_key']] = $def['href'] ?? '';
            }
        }

        $out = '';

        foreach ($node['children'] ?? [] as $child) {
            $text = $markdown ? ($child['text'] ?? '') : e($child['text'] ?? '');
            $marks = $child['marks'] ?? [];

            if (in_array('strong', $marks, true)) {
                $text = $markdown ? "**{$text}**" : "<strong>{$text}</strong>";
            }

            if (in_array('em', $marks, true)) {
                $text = $markdown ? "_{$text}_" : "<em>{$text}</em>";
            }

            foreach ($marks as $mark) {
                if (isset($hrefs[$mark])) {
                    $text = $markdown
                        ? "[{$text}]({$hrefs[$mark]})"
                        : '<a href="'.e($hrefs[$mark]).'">'.$text.'</a>';
                }
            }

            $out .= $text;
        }

        return $out;
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
     * Reparse `{tag options}` tokens out of a document's span text back into
     * dynamicTag nodes, using the same tokeniser {@see fromPlainText()} runs.
     * The editor has no node for a tag yet (see toProseMirror.js), so it round
     * trips one as this literal text; this is what turns it back into a node
     * before {@see ValidPortableText} sees it.
     *
     * @param  array<int, array<string, mixed>>  $document
     * @return array<int, array<string, mixed>>
     */
    public static function withTags(array $document): array
    {
        return array_map(fn (mixed $node): mixed => self::nodeWithTags($node), $document);
    }

    private static function nodeWithTags(mixed $node): mixed
    {
        if (! is_array($node) || ($node['_type'] ?? null) !== 'block' || ! is_array($node['children'] ?? null)) {
            return $node;
        }

        return [...$node, 'children' => self::childrenWithTags($node['children'])];
    }

    /**
     * @param  array<int, mixed>  $children
     * @return array<int, mixed>
     */
    private static function childrenWithTags(array $children): array
    {
        $result = [];

        foreach ($children as $child) {
            if (! is_array($child) || ($child['_type'] ?? 'span') !== 'span' || ! is_string($child['text'] ?? null)) {
                $result[] = $child;

                continue;
            }

            $tokens = self::tagTokens($child['text']);

            if ($tokens === []) {
                $result[] = $child;

                continue;
            }

            $marks = $child['marks'] ?? [];
            $cursor = 0;

            foreach ([...$tokens, ['offset' => strlen($child['text']), 'length' => 0, 'tag' => null, 'options' => []]] as $token) {
                $run = substr($child['text'], $cursor, $token['offset'] - $cursor);

                if ($run !== '') {
                    $result[] = self::span($run, $marks);
                }

                if ($token['tag'] !== null) {
                    $result[] = [
                        '_type' => 'dynamicTag',
                        '_key' => self::key(),
                        'tag' => $token['tag'],
                        'options' => $token['options'],
                    ];
                }

                $cursor = $token['offset'] + $token['length'];
            }
        }

        return $result;
    }

    /**
     * A paragraph with its dynamic tags lifted into nodes and the text between
     * them autolinked as usual.
     *
     * @return array<string, mixed>
     */
    private static function autolinked(string $paragraph): array
    {
        $tokens = self::tagTokens($paragraph);

        if ($tokens === []) {
            return self::autolinkedRun($paragraph);
        }

        $children = [];
        $markDefs = [];
        $cursor = 0;

        foreach ([...$tokens, ['offset' => strlen($paragraph), 'length' => 0, 'tag' => null, 'options' => []]] as $token) {
            $run = substr($paragraph, $cursor, $token['offset'] - $cursor);

            if ($run !== '') {
                $block = self::autolinkedRun($run);
                $children = [...$children, ...$block['children']];
                $markDefs = [...$markDefs, ...($block['markDefs'] ?? [])];
            }

            if ($token['tag'] !== null) {
                $children[] = [
                    '_type' => 'dynamicTag',
                    '_key' => self::key(),
                    'tag' => $token['tag'],
                    'options' => $token['options'],
                ];
            }

            $cursor = $token['offset'] + $token['length'];
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
     * Split a paragraph on `{tag key:value}` tokens, emitting a dynamicTag child
     * for each registered one. An unregistered tag stays literal text, so a typo
     * is visible rather than silently dropped.
     *
     * @return list<array{offset: int, length: int, tag: string, options: array<string, string>}>
     */
    private static function tagTokens(string $paragraph): array
    {
        $pattern = '/\{([a-z]+(?:\.[a-z]+)+)((?:\s+[a-z]+:[a-z0-9-]+)*)\}/i';

        if (preg_match_all($pattern, $paragraph, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $registry = app(DynamicTagRegistry::class);
        $tokens = [];

        foreach ($matches[0] as $index => [$match, $offset]) {
            $name = $matches[1][$index][0];

            if ($registry->find($name) === null) {
                continue;
            }

            $options = [];

            foreach (preg_split('/\s+/', trim($matches[2][$index][0])) ?: [] as $pair) {
                if ($pair !== '') {
                    [$key, $value] = explode(':', $pair, 2);
                    $options[$key] = $value;
                }
            }

            $tokens[] = ['offset' => $offset, 'length' => strlen($match), 'tag' => $name, 'options' => $options];
        }

        return $tokens;
    }

    /**
     * A paragraph with bare URLs turned into links. The editor does this itself
     * (Tiptap autolinks as you type), so this is for the clients that can only
     * send a string and would otherwise leave URLs as dead text.
     *
     * @return array<string, mixed>
     */
    private static function autolinkedRun(string $paragraph): array
    {
        preg_match_all('#\bhttps?://[^\s<>"\']+#i', $paragraph, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return [...self::block($paragraph), 'markDefs' => []];
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
