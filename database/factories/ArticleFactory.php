<?php

namespace Database\Factories;

use App\Models\Article;
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

        $paragraphs = fake()->paragraphs(fake()->numberBetween(3, 6));
        $content = implode("\n\n", array_map(
            fn (string $paragraph): string => $paragraph,
            $paragraphs,
        ));

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(fake()->numberBetween(10, 20)),
            'content' => $content,
            'draft' => fake()->boolean(10),
            'tags' => fake()->randomElements(
                ['Laravel', 'PHP', 'Web Development', 'Tutorial', 'DevOps'],
                fake()->numberBetween(1, 3),
            ),
        ];
    }
}
