<?php

namespace Database\Factories;

use App\Models\Note;
use App\Support\PortableText;
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
        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'content' => PortableText::fromPlainText(fake()->sentences(2, true)),
        ];
    }
}
