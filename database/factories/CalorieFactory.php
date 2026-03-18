<?php

namespace Database\Factories;

use App\Models\Calorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calorie>
 */
class CalorieFactory extends Factory
{
    /**
     * @var array<string, array<string, array{cal: int, fat: int, protein: int, carbs: int}>>
     */
    private const FOODS = [
        'breakfast' => [
            'Weetabix' => ['cal' => 190, 'fat' => 2, 'protein' => 6, 'carbs' => 36],
            'Porridge' => ['cal' => 220, 'fat' => 5, 'protein' => 8, 'carbs' => 35],
            'Toast' => ['cal' => 150, 'fat' => 3, 'protein' => 5, 'carbs' => 28],
            'Eggs on Toast' => ['cal' => 350, 'fat' => 18, 'protein' => 22, 'carbs' => 28],
            'Yoghurt' => ['cal' => 120, 'fat' => 4, 'protein' => 10, 'carbs' => 12],
            'Banana' => ['cal' => 105, 'fat' => 0, 'protein' => 1, 'carbs' => 27],
            'Coffee with Milk' => ['cal' => 50, 'fat' => 2, 'protein' => 3, 'carbs' => 5],
        ],
        'lunch' => [
            'Chicken Sandwich' => ['cal' => 420, 'fat' => 14, 'protein' => 32, 'carbs' => 40],
            'Tuna Wrap' => ['cal' => 380, 'fat' => 12, 'protein' => 28, 'carbs' => 38],
            'Soup' => ['cal' => 200, 'fat' => 6, 'protein' => 8, 'carbs' => 28],
            'Salad' => ['cal' => 250, 'fat' => 10, 'protein' => 15, 'carbs' => 20],
            'Pasta' => ['cal' => 500, 'fat' => 16, 'protein' => 20, 'carbs' => 65],
        ],
        'dinner' => [
            'Spaghetti Bolognese' => ['cal' => 620, 'fat' => 22, 'protein' => 30, 'carbs' => 70],
            'Chicken Stir Fry' => ['cal' => 480, 'fat' => 14, 'protein' => 35, 'carbs' => 50],
            'Fish and Chips' => ['cal' => 800, 'fat' => 35, 'protein' => 30, 'carbs' => 85],
            'Curry' => ['cal' => 550, 'fat' => 20, 'protein' => 28, 'carbs' => 55],
            'Roast Chicken' => ['cal' => 650, 'fat' => 25, 'protein' => 45, 'carbs' => 50],
        ],
        'snacks' => [
            'Apple' => ['cal' => 95, 'fat' => 0, 'protein' => 0, 'carbs' => 25],
            'Protein Bar' => ['cal' => 220, 'fat' => 8, 'protein' => 20, 'carbs' => 22],
            'Biscuits' => ['cal' => 150, 'fat' => 7, 'protein' => 2, 'carbs' => 20],
            'Crisps' => ['cal' => 180, 'fat' => 11, 'protein' => 2, 'carbs' => 18],
            'Nuts' => ['cal' => 170, 'fat' => 15, 'protein' => 6, 'carbs' => 6],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $meal = fake()->randomElement(array_keys(self::FOODS));
        $foods = self::FOODS[$meal];
        $name = fake()->randomElement(array_keys($foods));
        $macros = $foods[$name];
        $quantity = fake()->numberBetween(1, 3);

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'name' => $name,
            'meal' => $meal,
            'quantity' => $quantity,
            'units' => fake()->randomElement(['serving', 'piece', 'g']),
            'calories' => $macros['cal'] * $quantity,
            'fat' => $macros['fat'] * $quantity,
            'protein' => $macros['protein'] * $quantity,
            'carbs' => $macros['carbs'] * $quantity,
        ];
    }
}
