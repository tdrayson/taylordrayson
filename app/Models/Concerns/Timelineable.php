<?php

namespace App\Models\Concerns;

interface Timelineable
{
    public function slug(): string;

    public function url(): string;

    public function timezone(): ?string;

    /**
     * Whether this model's timeline entry should exist on the public spine.
     * Models with their own publication gate (e.g. Article) override this.
     */
    public function shouldAppearOnTimeline(): bool;

    /**
     * Whether the entry happened at a clock time rather than across a whole day.
     *
     * A day-granular type (food, and vitals when they arrive) is a total for
     * the day, complete only once the day is. It is stored at the end of that
     * day so ordering and its timezone have a real moment to work from, but a
     * clock reading would be a fiction the card should not show.
     */
    public function hasClockTime(): bool;
}
