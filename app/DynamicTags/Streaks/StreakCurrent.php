<?php

namespace App\DynamicTags\Streaks;

/** The run in progress: how many days, weeks, months or years back it reaches. */
class StreakCurrent extends StreakMeasure
{
    public function name(): string
    {
        return 'streak.current';
    }

    public function label(): string
    {
        return 'Current streak';
    }

    protected function measure(): string
    {
        return 'current';
    }
}
