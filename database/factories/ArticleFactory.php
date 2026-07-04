<?php

namespace Database\Factories;

use App\Models\Article;
use App\Support\EditorJs;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(fake()->numberBetween(4, 8));

        $blocks = [['type' => 'header', 'data' => ['text' => fake()->sentence(4), 'level' => 2]]];

        foreach (range(1, fake()->numberBetween(2, 4)) as $index) {
            $blocks[] = ['type' => 'paragraph', 'data' => ['text' => fake()->paragraph()]];

            if ($index === 1) {
                $blocks[] = ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => fake()->sentences(3)]];
            }
        }

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(fake()->numberBetween(10, 20)),
            'content' => EditorJs::document($blocks),
            'published' => fake()->boolean(90),
        ];
    }
}
