<?php

namespace Database\Factories;

use App\Enums\ActivityDiscipline;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Type keys are plain strings, mirroring the open `activity.type` set:
     * `activity.type` is not cast, and only the cardio disciplines carry
     * app behaviour (see ActivityDiscipline), so the rest are just test data.
     *
     * @var array<string, array{names: array<int, string>, cardio: bool}>
     */
    private const ACTIVITY_CONFIG = [
        ActivityDiscipline::Run->value => ['names' => ['Morning Run', 'Evening Run', 'Park Run', 'Tempo Run', 'Easy Run', 'Long Run'], 'cardio' => true],
        ActivityDiscipline::Ride->value => ['names' => ['Morning Ride', 'Evening Ride', 'Weekend Ride', 'Commute'], 'cardio' => true],
        ActivityDiscipline::Walk->value => ['names' => ['Morning Walk', 'Lunch Walk', 'Evening Walk', 'Sunday Walk'], 'cardio' => true],
        ActivityDiscipline::Swim->value => ['names' => ['Morning Swim', 'Pool Session', 'Open Water Swim'], 'cardio' => true],
        'weight-training' => ['names' => ['Upper Body', 'Lower Body', 'Full Body', 'Push Day', 'Pull Day', 'Leg Day', 'Core Session'], 'cardio' => false],
        'yoga' => ['names' => ['Morning Yoga', 'Full Body Yoga', 'Stretch & Recovery', 'Vinyasa Flow'], 'cardio' => false],
        'workout' => ['names' => ['Circuit Session', 'HIIT Class', 'Cross Training'], 'cardio' => false],
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

        $distance = $isCardio
            ? fake()->numberBetween(1000, 20000)
            : null;

        $meta = $isCardio
            ? ['elevation_gain' => fake()->numberBetween(10, 300)]
            : ['sets' => collect(fake()->randomElements(['Bench Press', 'Squat', 'Deadlift', 'Shoulder Press', 'Rows'], fake()->numberBetween(2, 4)))
                ->flatMap(fn (string $exercise): array => array_fill(0, 3, [
                    'exercise' => $exercise,
                    'reps' => fake()->numberBetween(6, 12),
                    'weight' => fake()->randomFloat(1, 10, 80),
                ]))
                ->values()
                ->all()];

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'type' => $type,
            'name' => fake()->randomElement($config['names']),
            'duration' => $durationSeconds,
            'calories' => fake()->numberBetween(100, 800),
            'distance' => $distance,
            'meta' => $meta,
        ];
    }
}
