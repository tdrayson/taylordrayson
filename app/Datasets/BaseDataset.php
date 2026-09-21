<?php

namespace App\Datasets;

use App\Enums\SpanAnchor;
use Illuminate\Support\Str;

/**
 * The defaults most datasets share, so each class states only what is particular to it.
 */
abstract class BaseDataset implements Dataset
{
    public function eyebrow(): ?string
    {
        return null;
    }

    public function noun(): string
    {
        return Str::lower(Str::singular($this->plural()));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        $noun = $this->noun();

        return [$noun, Str::plural($noun)];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return [];
    }

    public function taxonomy(): ?callable
    {
        return null;
    }

    public function stats(): bool
    {
        return false;
    }

    public function spanAnchor(): ?SpanAnchor
    {
        return null;
    }

    public function draftable(): bool
    {
        return false;
    }

    public function synced(): bool
    {
        return false;
    }
}
