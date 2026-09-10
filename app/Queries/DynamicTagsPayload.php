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
        return [
            'name' => $tag->name(),
            'label' => $tag->label(),
            'group' => $tag->group(),
            'supports' => array_map(fn ($placement): string => $placement->value, $tag->supports()),
            'options' => array_map(fn ($option): array => $option->toArray(), $tag->options()),
            // Resolved with defaults, so the menu can show what each tag reads
            // today rather than only its name.
            'preview' => $this->registry->value($tag->name(), $this->defaults($tag))['text'] ?? null,
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
