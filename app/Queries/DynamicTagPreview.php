<?php

namespace App\Queries;

use App\DynamicTags\DynamicTagRegistry;

/**
 * The display text one tag resolves to for an arbitrary option set, so the
 * options popup can show a live preview as an author changes a choice rather
 * than only ever showing each tag's default.
 */
final class DynamicTagPreview
{
    public function __construct(private readonly DynamicTagRegistry $registry) {}

    /**
     * Null when the tag is unregistered or has nothing to report for these options.
     *
     * @param  array<string, string>  $options
     */
    public function __invoke(string $name, array $options): ?string
    {
        return $this->registry->value($name, $options)['text'] ?? null;
    }
}
