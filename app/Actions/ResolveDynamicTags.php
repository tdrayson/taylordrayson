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
     * A null document resolves to an empty one rather than throwing. Empty
     * results are filtered out, since an unresolvable image node drops itself.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(?array $blocks): array
    {
        return array_values(array_filter(array_map($this->node(...), $blocks ?? [])));
    }

    /**
     * Recurses into callout children so a tag nested inside one still resolves.
     * An unresolvable image tag returns `[]`, dropping the node rather than
     * rendering a broken `<img>`.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(array $node): array
    {
        if (($node['_type'] ?? null) === 'image' && isset($node['tag'])) {
            $tag = $this->registry->find($node['tag']);
            $resolved = $this->registry->value($node['tag'], $node['options'] ?? []);

            if ($tag === null || $resolved === null) {
                return [];
            }

            $node['url'] = $tag->href($resolved['value'], $node['options'] ?? []);
            unset($node['tag'], $node['options']);

            return $node;
        }

        // Callouts nest blocks, so their children are walked too.
        if (($node['_type'] ?? null) === 'callout') {
            $node['children'] = $this($node['children'] ?? []);

            return $node;
        }

        if (isset($node['children'])) {
            $node['children'] = array_map($this->child(...), $node['children']);
        }

        if (isset($node['markDefs'])) {
            $node['markDefs'] = array_values(array_filter(
                array_map($this->markDef(...), $node['markDefs']),
            ));
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

    /**
     * A resolved dynamicHref becomes an ordinary link via `href()` (which can
     * differ from `format()`), keeping its `_key`; an unresolvable one is
     * dropped, which renderSpan handles by leaving the text unlinked.
     *
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>|null
     */
    private function markDef(array $def): ?array
    {
        if (($def['_type'] ?? null) !== 'dynamicHref') {
            return $def;
        }

        $tag = $this->registry->find($def['tag'] ?? '');
        $resolved = $this->registry->value($def['tag'] ?? '', $def['options'] ?? []);

        if ($tag === null || $resolved === null) {
            return null;
        }

        return ['_type' => 'link', '_key' => $def['_key'], 'href' => $tag->href($resolved['value'], $def['options'] ?? [])];
    }
}
