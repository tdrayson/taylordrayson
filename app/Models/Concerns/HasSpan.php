<?php

namespace App\Models\Concerns;

use App\Datasets\Datasets;
use App\Enums\SpanAnchor;
use Carbon\CarbonInterface;

/**
 * A span's two bounds, whichever end the model's dataset anchors occurred_at on.
 */
trait HasSpan
{
    public function spanStart(): ?CarbonInterface
    {
        return $this->anchorsOnEnd() ? $this->started_at : $this->occurred_at;
    }

    public function spanEnd(): ?CarbonInterface
    {
        return $this->anchorsOnEnd() ? $this->occurred_at : $this->ends_at;
    }

    private function anchorsOnEnd(): bool
    {
        return Datasets::forModel($this)?->spanAnchor() === SpanAnchor::End;
    }
}
