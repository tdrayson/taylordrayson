<?php

namespace Database\Factories;

use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * @var array<int, string>
     */
    private const TITLES = [
        'Las Vegas',
        'Tokyo',
        'Amsterdam',
        'New York',
        'Reykjavik',
        'Lisbon',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A trip title names a place and never a date; the slug carries a
        // counter so repeated places stay unique without dating the title.
        $title = fake()->randomElement(self::TITLES);
        $startsAt = CarbonImmutable::parse(fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d').' 08:00:00');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addDays(fake()->numberBetween(2, 13))->setTime(22, 0),
            'timezone' => 'Europe/London',
        ];
    }
}
