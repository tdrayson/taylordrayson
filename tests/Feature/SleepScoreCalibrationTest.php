<?php

use App\Models\Sleep;
use App\Support\Health\SleepProcessor;

/*
 * Calibration against Apple's own sleep scores for twelve real nights, captured
 * from the Health app before they aged out of it. The scorer approximates a
 * proprietary formula, so the bar is that it tracks Apple closely and without a
 * systematic lean, not that it matches night for night.
 *
 * The nights before 15 August are here to warm the bedtime baseline, which is a
 * rolling median of the previous fortnight. Scoring runs through
 * SleepProcessor::scoreAll so the baseline is built the way it is in production.
 */

/** occurred_at, bedtime, duration, awake, rem, core, deep — all seconds. */
const NIGHTS = [
    ['2026-07-21 05:46:34', '2026-07-21 03:50:25', 6849, 120, 0, 6849, 0],
    ['2026-07-22 12:28:29', '2026-07-21 23:57:29', 40980, 4080, 10620, 21390, 8970],
    ['2026-07-23 10:10:58', '2026-07-23 00:22:28', 32850, 2460, 7650, 18150, 7050],
    ['2026-07-24 09:39:30', '2026-07-24 02:57:30', 20670, 3450, 5520, 9060, 6090],
    ['2026-07-25 08:54:30', '2026-07-25 00:02:30', 29070, 2850, 7710, 15150, 6210],
    ['2026-07-26 12:08:32', '2026-07-26 00:25:32', 38760, 3420, 10290, 18660, 9810],
    ['2026-07-27 09:18:32', '2026-07-27 02:39:32', 20790, 3150, 4200, 11580, 5010],
    ['2026-07-28 05:18:28', '2026-07-27 23:50:28', 18090, 1590, 2790, 11580, 3720],
    ['2026-07-29 09:17:33', '2026-07-29 01:34:03', 23160, 4650, 3720, 12810, 6630],
    ['2026-07-30 05:13:52', '2026-07-30 01:13:03', 14208, 241, 3274, 8352, 2582],
    ['2026-07-31 09:44:47', '2026-07-31 01:11:06', 27998, 2823, 8411, 15260, 4327],
    ['2026-08-01 11:11:31', '2026-08-01 06:59:01', 13080, 2070, 2670, 8280, 2130],
    ['2026-08-02 10:26:28', '2026-08-02 00:40:28', 29040, 6120, 6420, 15270, 7350],
    ['2026-08-03 10:41:01', '2026-08-02 22:22:01', 39690, 4650, 9480, 19950, 10260],
    ['2026-08-04 04:58:58', '2026-08-03 23:22:58', 18600, 1560, 2910, 12630, 3060],
    ['2026-08-05 09:01:59', '2026-08-04 23:04:59', 30330, 5490, 6660, 13800, 9870],
    ['2026-08-06 06:43:29', '2026-08-05 22:58:59', 22800, 5070, 3870, 15750, 3180],
    ['2026-08-07 10:16:45', '2026-08-06 23:45:23', 36080, 1802, 3153, 24665, 5826],
    ['2026-08-09 08:27:41', '2026-08-09 00:42:34', 27067, 840, 5918, 19557, 1592],
    ['2026-08-10 09:40:07', '2026-08-09 23:08:46', 34427, 3454, 10755, 20187, 3485],
    ['2026-08-12 10:54:39', '2026-08-11 22:56:46', 38418, 4655, 8682, 24153, 3184],
    ['2026-08-13 10:35:12', '2026-08-12 23:23:19', 33284, 7029, 8112, 22768, 2404],
    ['2026-08-14 09:50:37', '2026-08-14 00:09:21', 30251, 4625, 7540, 19586, 3125],
    ['2026-08-15 08:56:41', '2026-08-14 22:42:51', 32204, 4626, 5228, 24632, 2344],
    ['2026-08-16 04:53:55', '2026-08-15 21:45:52', 25322, 361, 6039, 17541, 1742],
    ['2026-08-17 10:17:52', '2026-08-16 22:56:59', 34815, 6038, 9581, 21841, 3393],
    ['2026-08-18 09:54:52', '2026-08-17 22:07:28', 33009, 9435, 2794, 25681, 2373],
    ['2026-08-19 11:47:49', '2026-08-19 01:06:28', 31183, 7298, 6008, 22742, 2433],
    ['2026-08-20 05:39:24', '2026-08-20 03:55:46', 6068, 150, 0, 6068, 0],
    ['2026-08-21 09:05:26', '2026-08-21 00:14:45', 28837, 3004, 6940, 17721, 4176],
    ['2026-08-22 10:14:39', '2026-08-22 00:02:21', 32773, 3965, 7660, 21778, 3335],
    ['2026-08-23 11:06:39', '2026-08-23 05:23:12', 20427, 180, 4265, 13699, 2463],
    ['2026-08-24 11:30:29', '2026-08-24 00:17:42', 33668, 6699, 6638, 20519, 5435],
    ['2026-08-25 05:45:00', '2026-08-25 01:56:11', 11687, 2042, 2313, 7301, 2073],
    ['2026-08-26 12:50:56', '2026-08-26 00:15:56', 40823, 4477, 8921, 29017, 2885],
];

/** Apple's score for the nights it was recorded for. */
const APPLE = [
    '2026-08-15' => 84, '2026-08-16' => 93, '2026-08-17' => 80, '2026-08-18' => 79,
    '2026-08-19' => 57, '2026-08-20' => 22, '2026-08-21' => 87, '2026-08-22' => 86,
    '2026-08-23' => 52, '2026-08-24' => 80, '2026-08-25' => 43, '2026-08-26' => 82,
];

/**
 * Every scored night against Apple's, as [ours, theirs] keyed by date.
 *
 * @return array<string, array{int, int}>
 */
function scoredAgainstApple(): array
{
    foreach (NIGHTS as [$occurredAt, $bedtime, $duration, $awake, $rem, $core, $deep]) {
        Sleep::factory()->create([
            'occurred_at' => $occurredAt,
            'bedtime' => $bedtime,
            'wake_time' => $occurredAt,
            'duration' => $duration,
            'awake' => $awake,
            'rem' => $rem,
            'core' => $core,
            'deep' => $deep,
            'stages' => [],
            'source' => 'apple_watch',
        ]);
    }

    app(SleepProcessor::class)->scoreAll();

    $compared = [];

    foreach (Sleep::query()->orderBy('occurred_at')->get() as $night) {
        $date = $night->occurred_at->toDateString();

        if (isset(APPLE[$date])) {
            $compared[$date] = [(int) $night->score, APPLE[$date]];
        }
    }

    return $compared;
}

it('tracks Apple without leaning high or low', function () {
    $errors = array_map(fn (array $pair): int => $pair[0] - $pair[1], scoredAgainstApple());

    expect(count($errors))->toBe(12);

    $mean = array_sum($errors) / count($errors);
    $absolute = array_sum(array_map('abs', $errors)) / count($errors);

    // Before this calibration: mean -6.6, mean absolute 7.2, under on 11 of 12.
    expect(abs($mean))->toBeLessThan(2.0)
        ->and($absolute)->toBeLessThan(5.0);
});

it('lands within five points on most nights', function () {
    $errors = array_map(fn (array $pair): int => $pair[0] - $pair[1], scoredAgainstApple());
    $close = count(array_filter($errors, fn (int $error): bool => abs($error) <= 5));

    // Was 5 of 12. The nights it still misses are the short ones, where twelve
    // samples cannot say whether the curve or one odd night is wrong.
    expect($close)->toBeGreaterThanOrEqual(10);
});
