<?php

namespace App\Data\Aspects;

/** One eaten item as a receipt line: its name and calories, already formatted. */
final readonly class MealBreakdownItem
{
    public function __construct(
        public string $name,
        public string $calories,
    ) {}
}
