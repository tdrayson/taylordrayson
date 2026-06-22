<?php

namespace Database\Factories;

use App\Models\Sleep;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sleep>
 */
class SleepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurredAt = fake()->dateTimeBetween('-6 months');

        $bedHour = fake()->randomElement([22, 23, 0, 1]);
        $bedMinute = fake()->randomElement([0, 15, 30, 45]);
        $bedtime = Carbon::parse($occurredAt)->setTime($bedHour, $bedMinute);

        if ($bedHour >= 22) {
            $bedtime = $bedtime->subDay();
        }

        $wakeHour = fake()->numberBetween(5, 8);
        $wakeMinute = fake()->randomElement([0, 15, 30, 45]);
        $wakeTime = Carbon::parse($occurredAt)->setTime($wakeHour, $wakeMinute);

        $durationSeconds = (int) $bedtime->diffInSeconds($wakeTime);
        $durationSeconds = max($durationSeconds, 18000);
        $durationSeconds = min($durationSeconds, 36000);

        return [
            'occurred_at' => $occurredAt,
            'bedtime' => $bedtime,
            'wake_time' => $wakeTime,
            'duration' => $durationSeconds,
            'source' => fake()->randomElement(['oura', 'apple_watch', 'clock']),
            'stages' => null,
        ];
    }
}
