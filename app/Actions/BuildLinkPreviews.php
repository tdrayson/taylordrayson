<?php

namespace App\Actions;

use App\Links\LinkResolvers;

class BuildLinkPreviews
{
    public function __construct(private LinkResolvers $resolvers) {}

    /**
     * Build a deduped map of internal-link hrefs to preview cards from Portable
     * Text content. External and unresolvable targets are omitted, and the link
     * renders as an ordinary one.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<string, array<string, mixed>>
     */
    public function __invoke(?array $blocks): array
    {
        $previews = [];

        foreach ($this->hrefs($blocks) as $href) {
            $preview = $this->resolvers->resolve($href);

            if ($preview !== null) {
                $previews[$href] = $preview->toArray();
            }
        }

        return $previews;
    }

    /**
     * Every distinct internal href the document links to.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return list<string>
     */
    private function hrefs(?array $blocks): array
    {
        $hrefs = [];

        foreach ($blocks ?? [] as $block) {
            foreach ($block['markDefs'] ?? [] as $def) {
                $href = $def['href'] ?? null;

                if (($def['_type'] ?? null) === 'link' && is_string($href) && ! str_starts_with($href, 'http')) {
                    $hrefs[$href] = true;
                }
            }
        }

        return array_keys($hrefs);
    }
}
