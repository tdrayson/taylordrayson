<?php

namespace App\Models\Concerns;

interface Timelineable
{
    public function slug(): string;

    public function url(): string;

    public function timezone(): ?string;

    /**
     * Whether this model belongs on the spine at all, beyond its status.
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
