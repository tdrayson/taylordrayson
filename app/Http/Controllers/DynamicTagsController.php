<?php

namespace App\Http\Controllers;

use App\DynamicTags\DynamicTag;
use App\DynamicTags\DynamicTagRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Every registered tag, with the option schema the editor draws its form from
 * and a preview of what each resolves to right now.
 */
class DynamicTagsController extends Controller
{
    public function __invoke(DynamicTagRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_map(
                fn (DynamicTag $tag): array => $this->shape($tag, $registry),
                $registry->all(),
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(DynamicTag $tag, DynamicTagRegistry $registry): array
    {
        return [
            'name' => $tag->name(),
            'label' => $tag->label(),
            'group' => $tag->group(),
            'supports' => array_map(fn ($placement): string => $placement->value, $tag->supports()),
            'options' => array_map(fn ($option): array => $option->toArray(), $tag->options()),
            // Resolved with defaults, so the menu can show what each tag reads
            // today rather than only its name.
            'preview' => $registry->value($tag->name(), $this->defaults($tag))['text'] ?? null,
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
