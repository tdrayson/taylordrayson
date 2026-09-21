<?php

namespace App\Data\Aspects;

/**
 * A day's food broken down by meal, with every item's name and calorie
 * string already formatted by the export. Its presence is what lets
 * FoodSheet arrange per-meal rows without reaching into raw item data.
 */
final readonly class MealBreakdown
{
    /**
     * @param  list<MealBreakdownMeal>  $meals
     */
    private function __construct(
        public array $meals,
    ) {}

    /**
     * @param  list<MealBreakdownMeal>  $meals
     */
    public static function make(array $meals): ?self
    {
        return $meals === [] ? null : new self($meals);
    }
}
