<?php

namespace App\Models\Concerns;

use App\Data\CardData;

interface Timelineable
{
    /**
     * The timeline card payload: type/icon/title/subtitle plus accent, an
     * optional multi-day range, and the type-specific `meta` block.
     */
    public function card(): CardData;

    public function slug(): string;

    public function url(): string;

    public function timezone(): ?string;

    /**
     * Whether this model's timeline entry should exist on the public spine.
     * Models with their own publication gate (e.g. Article) override this.
     */
    public function shouldAppearOnTimeline(): bool;
}
