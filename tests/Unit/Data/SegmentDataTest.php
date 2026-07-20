<?php

use App\Data\SegmentData;

it('serialises to label, stage, seconds', function () {
    $segment = new SegmentData('Awake', 'awake', 1620);

    expect($segment->toArray())->toBe(['label' => 'Awake', 'stage' => 'awake', 'seconds' => 1620]);
});
