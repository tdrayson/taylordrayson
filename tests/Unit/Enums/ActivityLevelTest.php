<?php

use App\Enums\ActivityLevel;

it('shades a day by how many entries it has', function (int $count, ActivityLevel $level) {
    expect(ActivityLevel::fromCount($count))->toBe($level);
})->with([
    [0, ActivityLevel::None],
    [1, ActivityLevel::Light],
    [2, ActivityLevel::Light],
    [3, ActivityLevel::Busy],
    [4, ActivityLevel::Busy],
    [5, ActivityLevel::Full],
    [40, ActivityLevel::Full],
]);
