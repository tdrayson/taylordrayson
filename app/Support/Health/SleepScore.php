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
    /** Asleep target (minutes) where duration scores full marks: 7h50m. */
    private const DURATION_TARGET = 470;

    /** Points lost per hour slept beyond the target: a long night is not a better one. */
    private const SURPLUS_RATE = 3;

    /** Bedtime later than this many minutes past the grace window starts costing points. */
    private const BEDTIME_GRACE = 15;

    /** Awake share of a night not held against it. */
    private const AWAKE_GRACE = 0.05;

    /** Awake share at which the interruption component reaches nothing. */
    private const AWAKE_FLOOR = 0.35;

    /**
     * Score one night.
     *
     * @param  array{duration:int, awake:int, rem:int, core:int, deep:int, bedtime_minutes:int, baseline_minutes:?int}  $night
     * @return array{score:int, duration_score:int, bedtime_score:int, interruption_score:int}
     */
    public function score(array $night): array
    {
        $duration = $this->durationScore($night);
        $bedtime = $this->bedtimeScore($night['bedtime_minutes'], $night['baseline_minutes']);
        $interruption = $this->interruptionScore($night['awake'], $night['duration']);

        return [
            'score' => $duration + $bedtime + $interruption,
            'duration_score' => $duration,
            'bedtime_score' => $bedtime,
            'interruption_score' => $interruption,
        ];
    }

    /**
     * Duration component (max 50): a non-linear penalty for sleeping under the
     * target, a mild flat one for sleeping well over it, and 5 off each for low
     * deep and low REM when the night is staged.
     *
     * Both stage minimums are read against the target rather than the night's
     * own length. Measured as a share of what was actually slept, a long night
     * has to produce proportionally more deep sleep to escape the penalty,
     * which made every lie-in score worse than an ordinary night.
     *
     * @param  array{duration:int, rem:int, deep:int}  $night
     */
    private function durationScore(array $night): int
    {
        $asleep = $night['duration'] / 60;
        $deficitHours = max(0, self::DURATION_TARGET - $asleep) / 60;
        $surplusHours = max(0, $asleep - self::DURATION_TARGET) / 60;

        $deduction = 5 * $deficitHours ** 1.38 + self::SURPLUS_RATE * $surplusHours;

        $reference = min($asleep, self::DURATION_TARGET);
        $staged = $night['rem'] > 0 || $night['deep'] > 0;

        if ($staged && 0.10 * $reference > $night['deep'] / 60) {
            $deduction += 5;
        }

        if ($staged && 0.15 * $reference > $night['rem'] / 60) {
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
     * Interruption component (max 20): awake time as a share of the night
     * rather than as a count of minutes.
     *
     * An hour awake inside eleven hours in bed is not the same night as an hour
     * awake inside six, and scoring them alike drove this component to nothing
     * on half of every long night. Wake-ups are no longer counted separately:
     * their minutes are already here, and charging for both penalised the same
     * interruptions twice.
     */
    private function interruptionScore(int $awakeSeconds, int $asleepSeconds): int
    {
        $inBed = $awakeSeconds + $asleepSeconds;

        if ($inBed <= 0) {
            return 20;
        }

        $over = max(0, ($awakeSeconds / $inBed) - self::AWAKE_GRACE);
        $deduction = 20 * $over / (self::AWAKE_FLOOR - self::AWAKE_GRACE);

        return (int) round($this->clamp(20 - $deduction, 0, 20));
    }

    private function clamp(float $value, float $low, float $high): float
    {
        return max($low, min($high, $value));
    }
}
