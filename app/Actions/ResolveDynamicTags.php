<?php

namespace App\Actions;

use App\DynamicTags\DynamicTagRegistry;

/**
 * Rewrite every dynamic tag in a document into an ordinary node, so the
 * renderer, plainText(), the feeds and OG all read a tag without knowing one
 * exists. Runs at render; nothing here is ever saved.
 */
class ResolveDynamicTags
{
    public function __construct(private readonly DynamicTagRegistry $registry) {}

    /**
     * A null document resolves to an empty one rather than throwing.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(?array $blocks): array
    {
        return array_map($this->node(...), $blocks ?? []);
    }

    /**
     * Recurses into callout children so a tag nested inside one still resolves.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(array $node): array
    {
        // Callouts nest blocks, so their children are walked too.
        if (($node['_type'] ?? null) === 'callout') {
            $node['children'] = $this($node['children'] ?? []);

            return $node;
        }

        if (isset($node['children'])) {
            $node['children'] = array_map($this->child(...), $node['children']);
        }

        return $node;
    }

    /**
     * Non-tag children pass through unchanged; a dynamicTag child is rewritten
     * into a span, with `dynamicTag` metadata omitted when it fails to resolve.
     *
     * @param  array<string, mixed>  $child
     * @return array<string, mixed>
     */
    private function child(array $child): array
    {
        if (($child['_type'] ?? null) !== 'dynamicTag') {
            return $child;
        }

        $resolved = $this->registry->value($child['tag'] ?? '', $child['options'] ?? []);

        $span = [
            '_type' => 'span',
            '_key' => $child['_key'],
            'text' => $resolved['text'] ?? '',
            'marks' => [],
        ];

        if ($resolved !== null) {
            $span['dynamicTag'] = ['tag' => $child['tag'], 'value' => $resolved['value']];
        }

        return $span;
    }
}
