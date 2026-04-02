<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @var array<string, array{names: array<int, string>, cardio: bool}>
     */
    private const ACTIVITY_CONFIG = [
        'run' => ['names' => ['Morning Run', 'Evening Run', 'Park Run', 'Tempo Run', 'Easy Run', 'Long Run'], 'cardio' => true],
        'ride' => ['names' => ['Morning Ride', 'Evening Ride', 'Weekend Ride', 'Commute'], 'cardio' => true],
        'walk' => ['names' => ['Morning Walk', 'Lunch Walk', 'Evening Walk', 'Sunday Walk'], 'cardio' => true],
        'swim' => ['names' => ['Morning Swim', 'Pool Session', 'Open Water Swim'], 'cardio' => true],
        'gym' => ['names' => ['Upper Body', 'Lower Body', 'Full Body', 'Push Day', 'Pull Day', 'Leg Day', 'Core Session'], 'cardio' => false],
        'yoga' => ['names' => ['Morning Yoga', 'Full Body Yoga', 'Stretch & Recovery', 'Vinyasa Flow'], 'cardio' => false],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(self::ACTIVITY_CONFIG));
        $config = self::ACTIVITY_CONFIG[$type];
        $isCardio = $config['cardio'];

        $durationSeconds = $isCardio
            ? fake()->numberBetween(900, 7200)
            : fake()->numberBetween(1800, 5400);

        $distanceKm = $isCardio
            ? round(fake()->randomFloat(2, 3, 21), 2)
            : null;

        $meta = $isCardio
            ? ['elevation_gain' => fake()->numberBetween(10, 300)]
            : ['exercises' => fake()->randomElements(['Bench Press', 'Squat', 'Deadlift', 'Pull Up', 'Shoulder Press', 'Rows', 'Lunges', 'Plank'], fake()->numberBetween(3, 6))];

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'type' => $type,
            'name' => fake()->randomElement($config['names']),
            'duration' => $durationSeconds,
            'calories' => fake()->numberBetween(100, 800),
            'distance_km' => $distanceKm,
            'meta' => $meta,
        ];
    }
}
