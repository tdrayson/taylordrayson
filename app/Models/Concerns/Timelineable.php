<?php

namespace App\Models\Concerns;

use Carbon\Carbon;

interface Timelineable
{
    /**
     * @return array{
     *     type: string,
     *     icon: string,
     *     title: string,
     *     subtitle: ?string,
     *     occurred_at: Carbon,
     *     accent: string,
     *     meta: array,
     * }
     */
    public function card(): array;

    public function slug(): string;

    /**
     * The slug as it appears in the entry URL. Machine types append the id so
     * same-day name collisions stay addressable; types with an author-managed
     * unique slug (e.g. Article) override to keep the bare slug.
     */
    public function urlSlug(): string;

    public function url(): string;

    public function timezone(): ?string;

    /**
     * Whether this model's timeline entry should exist on the public spine.
     * Models with their own publication gate (e.g. Article) override this.
     */
    public function shouldAppearOnTimeline(): bool;
}
