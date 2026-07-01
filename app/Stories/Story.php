<?php

namespace App\Stories;

use App\Http\Controllers\StoryController;

/**
 * A data story: a slug-routed long-read page plus the live figures behind it.
 * Implement this for each story (fuel, food, sleep, …) and register it in
 * {@see StoryRegistry}; the single {@see StoryController}
 * then serves it with no per-story controller.
 */
interface Story
{
    /** The URL slug, e.g. 'fuel' for /stories/fuel. */
    public function slug(): string;

    /** The Inertia page component to render, e.g. 'Stories/Fuel'. */
    public function component(): string;

    /**
     * The compact card shown in the /stories archive listing.
     *
     * @return array{slug: string, type: string, title: string, description: string, accent: string}
     */
    public function card(): array;

    /**
     * The Open Graph / document metadata payload.
     *
     * @return array<string, mixed>
     */
    public function og(): array;

    /**
     * The computed figures and chart series handed to the page as `story`.
     *
     * @return array<string, mixed>
     */
    public function build(): array;
}
