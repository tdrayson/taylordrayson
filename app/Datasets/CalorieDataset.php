<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Calorie;
use App\Presenters\Cards\CalorieCard;

/**
 * Daily food logging, from MyFitnessPal.
 */
final class CalorieDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Calorie;
    }

    public function model(): string
    {
        return Calorie::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Health;
    }

    public function icon(): string
    {
        return 'UtensilsIcon';
    }

    public function label(): string
    {
        return 'Food';
    }

    public function plural(): string
    {
        return 'Food';
    }

    public function slug(): string
    {
        return 'food';
    }

    public function keywords(): string
    {
        return 'food eat meal nutrition';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function countNouns(): array
    {
        return ['day', 'days'];
    }

    public function card(): CalorieCard
    {
        return new CalorieCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        // Food is the only type whose entry is a whole day rather than a row,
        // so each field says which it means. `scope` drives the compiler; the
        // category says the same thing to the person choosing.
        return [
            'calories' => ['label' => 'Total calories', 'dataType' => 'number', 'column' => 'calories', 'category' => 'For the day', 'suffix' => 'kcal', 'scope' => 'day'],
            'protein' => ['label' => 'Total protein', 'dataType' => 'number', 'column' => 'protein', 'category' => 'For the day', 'suffix' => 'g', 'scope' => 'day'],
            'carbs' => ['label' => 'Total carbs', 'dataType' => 'number', 'column' => 'carbs', 'category' => 'For the day', 'suffix' => 'g', 'scope' => 'day'],
            'fat' => ['label' => 'Total fat', 'dataType' => 'number', 'column' => 'fat', 'category' => 'For the day', 'suffix' => 'g', 'scope' => 'day'],
            'sugars' => ['label' => 'Total sugars', 'dataType' => 'number', 'column' => 'sugars', 'category' => 'For the day', 'suffix' => 'g', 'scope' => 'day'],
            'item_name' => ['label' => 'Name', 'dataType' => 'text', 'column' => 'name', 'category' => 'Any food item', 'scope' => 'item'],
            'item_meal' => ['label' => 'Meal', 'dataType' => 'enum', 'column' => 'meal', 'category' => 'Any food item', 'scope' => 'item'],
            'item_calories' => ['label' => 'Calories', 'dataType' => 'number', 'column' => 'calories', 'category' => 'Any food item', 'suffix' => 'kcal', 'scope' => 'item'],
            'item_protein' => ['label' => 'Protein', 'dataType' => 'number', 'column' => 'protein', 'category' => 'Any food item', 'suffix' => 'g', 'scope' => 'item'],
            'item_carbs' => ['label' => 'Carbs', 'dataType' => 'number', 'column' => 'carbs', 'category' => 'Any food item', 'suffix' => 'g', 'scope' => 'item'],
            'item_fat' => ['label' => 'Fat', 'dataType' => 'number', 'column' => 'fat', 'category' => 'Any food item', 'suffix' => 'g', 'scope' => 'item'],
            'item_sugars' => ['label' => 'Sugars', 'dataType' => 'number', 'column' => 'sugars', 'category' => 'Any food item', 'suffix' => 'g', 'scope' => 'item'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['name', 'meal'];
    }
}
