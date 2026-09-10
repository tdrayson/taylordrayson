<?php

namespace App\Queries;

use App\DynamicTags\DynamicTag;
use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;

/**
 * What one tag currently reads as for a given placement and option set, so the
 * options popup can show a live preview as an author changes a choice. `href`
 * and `image` ask for the resolved URL, `inline` for the display text; the two
 * differ whenever a tag overrides {@see DynamicTag::href()}.
 */
final class DynamicTagPreview
{
    public function __construct(private readonly DynamicTagRegistry $registry) {}

    /**
     * Null when the tag is unregistered or has nothing to report for these options.
     *
     * @param  array<string, string>  $options
     */
    public function __invoke(string $name, array $options, Placement $placement): ?string
    {
        $tag = $this->registry->find($name);
        $resolved = $this->registry->value($name, $options);

        if ($tag === null || $resolved === null) {
            return null;
        }

        return $placement === Placement::Inline
            ? $resolved['text']
            : $tag->href($resolved['value'], $options);
    }
}
