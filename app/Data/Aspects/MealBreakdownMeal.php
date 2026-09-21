<?php

namespace App\Data\Aspects;

/** One meal's items under its label, both already formatted. */
final readonly class MealBreakdownMeal
{
    /**
     * @param  list<MealBreakdownItem>  $items
     */
    public function __construct(
        public string $label,
        public array $items,
    ) {}
}
