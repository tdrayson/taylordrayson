<?php

namespace App\Support\Health;

/**
 * Approximates Apple Health's 0-100 sleep score from our stored sleep data.
 *
 * Apple's exact formula is proprietary; this follows the publicly documented
 * breakdown (duration 50, bedtime 30, interruptions 20) so our score lands
 * close to Apple's without claiming to match it exactly. The /sleep-score page
 * explains the thresholds chosen here in plain language.
 */
class SleepScore
{
    /** Asleep target (minutes) above which duration is not penalised: 7h50m. */
    private const DURATION_TARGET = 470;

    /** Bedtime later than this many minutes past the grace window starts costing points. */
    private const BEDTIME_GRACE = 15;

    /** Awake minutes tolerated before the interruption penalty begins. */
    private const AWAKE_GRACE = 11;

    /**
     * Score one night.
     *
     * @param  array{duration:int, awake:int, rem:int, core:int, deep:int, wake_events:int, bedtime_minutes:int, baseline_minutes:?int}  $night
     * @return array{score:int, duration_score:int, bedtime_score:int, interruption_score:int}
     */
    public function score(array $night): array
    {
        $duration = $this->durationScore($night);
        $bedtime = $this->bedtimeScore($night['bedtime_minutes'], $night['baseline_minutes']);
        $interruption = $this->interruptionScore($night['awake'], $night['wake_events']);

        return [
            'score' => $duration + $bedtime + $interruption,
            'duration_score' => $duration,
            'bedtime_score' => $bedtime,
            'interruption_score' => $interruption,
        ];
    }

    /**
     * Duration component (max 50): a non-linear penalty for sleeping under the
     * target, plus 5 off each for low deep and low REM when the night is staged.
     *
     * @param  array{duration:int, rem:int, deep:int}  $night
     */
    private function durationScore(array $night): int
    {
        $asleep = $night['duration'] / 60;
        $deficitHours = max(0, self::DURATION_TARGET - $asleep) / 60;

        $deduction = 5 * $deficitHours ** 1.38;

        $staged = $night['rem'] > 0 || $night['deep'] > 0;

        if ($staged && 0.10 * $asleep > $night['deep'] / 60) {
            $deduction += 5;
        }

        if ($staged && 0.15 * $asleep > $night['rem'] / 60) {
            $deduction += 5;
        }

        return (int) round($this->clamp(50 - $deduction, 0, 50));
    }

    /**
     * Bedtime component (max 30): no penalty for on-time or up to an hour early;
     * ~1 point per 5 minutes late beyond a grace window; a small penalty for
     * being very early. Full marks when there is no baseline yet.
     */
    private function bedtimeScore(int $bedtimeMinutes, ?int $baselineMinutes): int
    {
        if ($baselineMinutes === null) {
            return 30;
        }

        $delta = $bedtimeMinutes - $baselineMinutes;
        $deduction = 0.0;

        if ($delta > self::BEDTIME_GRACE) {
            $deduction = ($delta - self::BEDTIME_GRACE) * 0.222;
        } elseif ($delta < -60) {
            $deduction = min(6, (abs($delta) - 60) / 30);
        }

        return (int) round($this->clamp(30 - $deduction, 0, 30));
    }

    /**
     * Interruption component (max 20): awake time over the grace window at
     * ~1 point per 4 minutes, plus ~1 point per 2 wake-ups beyond the first two.
     */
    private function interruptionScore(int $awakeSeconds, int $wakeEvents): int
    {
        $awakeMinutes = $awakeSeconds / 60;

        $deduction = max(0, ($awakeMinutes - self::AWAKE_GRACE) / 4)
            + max(0, ($wakeEvents - 2) / 2);

        return (int) round($this->clamp(20 - $deduction, 0, 20));
    }

    private function clamp(float $value, float $low, float $high): float
    {
        return max($low, min($high, $value));
    }
}
