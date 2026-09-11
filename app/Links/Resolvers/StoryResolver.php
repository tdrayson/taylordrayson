<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Enums\TimelineType;
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

        // The card's own `accent` is a hex, which is what the /stories listing
        // paints with. A preview wants the token, so take it from the type.
        $accent = TimelineType::tryFrom($card['type'])?->accent() ?? 'article';

        return LinkPreviewData::story($path, $card['title'], $card['description'], $accent);
    }
}
