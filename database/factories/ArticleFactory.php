<?php

namespace Database\Factories;

use App\Models\Article;
use App\Support\PortableText;
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

        $blocks = [PortableText::block(fake()->sentence(4), 'h2')];

        foreach (range(1, fake()->numberBetween(2, 4)) as $index) {
            $blocks[] = PortableText::block(fake()->paragraph());

            if ($index === 1) {
                foreach (fake()->sentences(3) as $item) {
                    $blocks[] = PortableText::block($item, 'normal', 'bullet');
                }
            }
        }

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(fake()->numberBetween(10, 20)),
            'content' => $blocks,
            'published' => fake()->boolean(90),
        ];
    }
}
