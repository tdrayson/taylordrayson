<?php

namespace Database\Factories;

use App\Models\Note;
use App\Support\EditorJs;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blocks = [];

        foreach (range(1, fake()->numberBetween(1, 2)) as $ignored) {
            $blocks[] = ['type' => 'paragraph', 'data' => ['text' => fake()->sentences(fake()->numberBetween(1, 3), true)]];
        }

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'content' => EditorJs::document($blocks),
        ];
    }
}
