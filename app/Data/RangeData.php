<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * An event's multi-day span for the timeline card and entry badge. `label` is
 * the compact card form ("2-4 Jun 2022"); `long` is the spelled-out detail
 * form ("2nd to 4th June 2022").
 */
final readonly class RangeData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $start,
        public string $end,
        public int $days,
        public string $label,
        public string $long,
    ) {}

    /**
     * @return array{start: string, end: string, days: int, label: string, long: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'days' => $this->days,
            'label' => $this->label,
            'long' => $this->long,
        ];
    }

    /**
     * @return array{start: string, end: string, days: int, label: string, long: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
