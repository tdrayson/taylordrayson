<?php

namespace Database\Factories;

use App\Enums\EntryStatus;
use App\Models\Page;
use App\Support\PortableText;
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
                PortableText::block(rtrim($title, '.'), 'h2'),
                PortableText::block($this->faker->paragraph()),
            ],
            'status' => EntryStatus::Published,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => EntryStatus::Published]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => EntryStatus::Draft]);
    }
}
