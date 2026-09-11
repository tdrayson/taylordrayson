<?php

namespace App\Actions\Files;

use App\Support\PortableText;

/**
 * Resolve every GitHub file block in a document to the release behind it, keyed
 * `repo#asset` because that pair is what a block names and the card looks up.
 *
 * Read-only and cache-backed, so a document full of downloads costs no requests.
 */
final class BuildFileReleases
{
    public function __construct(private ResolveReleaseAsset $resolve) {}

    /**
     * @param  array<int, array<string, mixed>>|string|null  $document
     * @return array<string, array<string, mixed>>
     */
    public function __invoke(array|string|null $document): array
    {
        $releases = [];

        foreach (PortableText::nodes($document) as $node) {
            $repo = $node['repo'] ?? null;
            $asset = $node['asset'] ?? null;

            if (($node['_type'] ?? null) !== 'file' || ($node['source'] ?? null) !== 'github') {
                continue;
            }

            if (is_string($repo) && is_string($asset)) {
                $releases["{$repo}#{$asset}"] = ($this->resolve)($repo, $asset)->toArray();
            }
        }

        return $releases;
    }
}
