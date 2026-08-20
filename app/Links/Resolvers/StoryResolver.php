<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Links\LinkResolver;
use App\Stories\StoryRegistry;

/**
 * A data story, /stories/{slug}. Not model-backed, so it resolves through the
 * registry that serves the story pages themselves.
 */
class StoryResolver implements LinkResolver
{
    public function __construct(private StoryRegistry $stories) {}

    public function resolve(string $path): ?LinkPreviewData
    {
        if (preg_match('#^/stories/([a-z0-9-]+)$#', $path, $matches) !== 1) {
            return null;
        }

        $story = $this->stories->find($matches[1]);

        if ($story === null) {
            return null;
        }

        $card = $story->card();

        return LinkPreviewData::story($path, $card['title'], $card['description'], $card['accent']);
    }
}
