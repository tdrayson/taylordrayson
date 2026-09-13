<?php

namespace App\DynamicTags\Streaks;

/** The longest run ever, whether or not it is the one in progress. */
class StreakLongest extends StreakMeasure
{
    public function name(): string
    {
        return 'streak.longest';
    }

    public function label(): string
    {
        return 'Longest streak';
    }

    protected function measure(): string
    {
        return 'longest';
    }
}
