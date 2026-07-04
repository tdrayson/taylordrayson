<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'excerpt' => $this->faker->sentence(),
            'content' => [
                'blocks' => [
                    ['type' => 'header', 'data' => ['text' => rtrim($title, '.'), 'level' => 2]],
                    ['type' => 'paragraph', 'data' => ['text' => $this->faker->paragraph()]],
                ],
            ],
            'published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published' => false]);
    }
}
