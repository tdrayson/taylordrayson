<?php

namespace App\Models\Concerns;

use App\Datasets\Datasets;
use App\Enums\SpanAnchor;
use Carbon\CarbonInterface;
use LogicException;

/**
 * A span's two bounds, whichever end the model's dataset anchors occurred_at on.
 */
trait HasSpan
{
    public function spanStart(): ?CarbonInterface
    {
        return $this->spanAnchor() === SpanAnchor::End ? $this->started_at : $this->occurred_at;
    }

    public function spanEnd(): ?CarbonInterface
    {
        return $this->spanAnchor() === SpanAnchor::End ? $this->occurred_at : $this->ends_at;
    }

    private function spanAnchor(): SpanAnchor
    {
        return Datasets::forModel($this)?->spanAnchor()
            ?? throw new LogicException(static::class.' uses HasSpan but its dataset declares no span anchor.');
    }
}
