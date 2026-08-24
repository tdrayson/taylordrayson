<?php

namespace App\Actions;

use App\Links\LinkResolvers;
use App\Support\Links;

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

        foreach ($this->hrefs($blocks) as $href => $path) {
            $preview = $this->resolvers->resolve($path);

            if ($preview !== null) {
                $previews[$href] = $preview->toArray();
            }
        }

        return $previews;
    }

    /**
     * Every internal link in the document, as written => the path it resolves
     * against. Keyed by the href as written because that is what the renderer
     * looks the preview up by.
     *
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<string, string>
     */
    private function hrefs(?array $blocks): array
    {
        $hrefs = [];

        foreach ($blocks ?? [] as $block) {
            foreach ($block['markDefs'] ?? [] as $def) {
                $href = $def['href'] ?? null;

                if (($def['_type'] ?? null) !== 'link' || ! is_string($href)) {
                    continue;
                }

                $path = Links::internalPath($href);

                if ($path !== null) {
                    $hrefs[$href] = $path;
                }
            }
        }

        return $hrefs;
    }
}
