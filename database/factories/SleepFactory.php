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

        $durationMinutes = (int) $bedtime->diffInMinutes($wakeTime);
        $durationMinutes = max($durationMinutes, 300);
        $durationMinutes = min($durationMinutes, 600);

        $awakeMinutes = fake()->numberBetween(10, 40);
        $remaining = $durationMinutes - $awakeMinutes;
        $remMinutes = (int) round($remaining * fake()->randomFloat(2, 0.18, 0.25));
        $deepMinutes = (int) round($remaining * fake()->randomFloat(2, 0.12, 0.20));
        $coreMinutes = $remaining - $remMinutes - $deepMinutes;

        $stages = [
            ['stage' => 'awake', 'minutes' => $awakeMinutes],
            ['stage' => 'core', 'minutes' => (int) round($coreMinutes * 0.4)],
            ['stage' => 'deep', 'minutes' => $deepMinutes],
            ['stage' => 'core', 'minutes' => (int) round($coreMinutes * 0.3)],
            ['stage' => 'rem', 'minutes' => $remMinutes],
            ['stage' => 'core', 'minutes' => $coreMinutes - (int) round($coreMinutes * 0.4) - (int) round($coreMinutes * 0.3)],
        ];

        return [
            'occurred_at' => $occurredAt,
            'bedtime' => $bedtime,
            'wake_time' => $wakeTime,
            'duration_minutes' => $durationMinutes,
            'awake_minutes' => $awakeMinutes,
            'rem_minutes' => $remMinutes,
            'core_minutes' => $coreMinutes,
            'deep_minutes' => $deepMinutes,
            'stages' => $stages,
            'source' => fake()->randomElement(['apple_watch', 'manual']),
        ];
    }
}
