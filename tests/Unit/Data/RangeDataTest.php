<?php

use App\Data\RangeData;

it('serialises to the five expected keys', function () {
    $range = new RangeData('2022-06-02', '2022-06-04', 3, '2-4 Jun 2022', '2nd to 4th June 2022');

    expect($range->toArray())->toBe([
        'start' => '2022-06-02',
        'end' => '2022-06-04',
        'days' => 3,
        'label' => '2-4 Jun 2022',
        'long' => '2nd to 4th June 2022',
    ]);
});
