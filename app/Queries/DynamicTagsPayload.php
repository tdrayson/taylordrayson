<?php

namespace App\Queries;

use App\DynamicTags\DynamicTag;
use App\DynamicTags\DynamicTagRegistry;

/**
 * Every registered tag, shaped into the schema the tag editor draws its form
 * from, with a preview of what each currently resolves to.
 */
final class DynamicTagsPayload
{
    /** Shown instead of a preview when a tag needs an option that was never chosen. */
    private const PREVIEW_NOT_SET = 'Not set';

    /** Shown instead of a preview when a tag resolves against empty source data. */
    private const PREVIEW_NO_DATA = 'No data';

    public function __construct(private readonly DynamicTagRegistry $registry) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(): array
    {
        return array_values(array_map(
            fn (DynamicTag $tag): array => $this->shape($tag),
            $this->registry->all(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(DynamicTag $tag): array
    {
        // Resolved with defaults, so the menu can show what each tag reads
        // today rather than only its name.
        $resolved = $this->registry->value($tag->name(), $this->defaults($tag));

        return [
            'name' => $tag->name(),
            'label' => $tag->label(),
            'group' => $tag->group(),
            'subgroup' => $tag->subgroup(),
            'supports' => array_map(fn ($placement): string => $placement->value, $tag->supports()),
            'options' => array_map(fn ($option): array => $option->toArray(), $tag->options()),
            'preview' => $resolved['text'] ?? ($tag->needsOption() ? self::PREVIEW_NOT_SET : self::PREVIEW_NO_DATA),
            // Lets the menu style a placeholder preview differently from a
            // real one without guessing from the text alone.
            'previewResolved' => $resolved !== null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaults(DynamicTag $tag): array
    {
        $defaults = [];

        foreach ($tag->options() as $option) {
            if ($option->default !== null) {
                $defaults[$option->name] = $option->default;
            }
        }

        return $defaults;
    }
}
