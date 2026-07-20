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
}
