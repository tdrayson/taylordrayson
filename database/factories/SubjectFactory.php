<?php

namespace Database\Factories;

use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = fake()->randomElement(SubjectKind::cases());
        $name = fake()->firstName();

        return [
            'kind' => $kind,
            'category' => fake()->randomElement(SubjectCategory::forKind($kind)),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
        ];
    }

    public function person(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => SubjectKind::Person,
            'category' => fake()->randomElement(SubjectCategory::forKind(SubjectKind::Person)),
        ]);
    }

    public function pet(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => SubjectKind::Pet,
            'category' => fake()->randomElement(SubjectCategory::forKind(SubjectKind::Pet)),
        ]);
    }

    public function spot(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => SubjectKind::Spot,
            'category' => fake()->randomElement(SubjectCategory::forKind(SubjectKind::Spot)),
            'latitude' => fake()->latitude(50, 58),
            'longitude' => fake()->longitude(-5, 2),
        ]);
    }

    public function thing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => SubjectKind::Thing,
            'category' => fake()->randomElement(SubjectCategory::forKind(SubjectKind::Thing)),
            'meta' => [
                ['label' => 'Bought', 'value' => fake()->year()],
            ],
        ]);
    }
}
