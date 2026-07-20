<?php

use App\Support\Distance;

it('converts metres for display', function () {
    expect(Distance::km(5230))->toBe(5.2)
        ->and(Distance::miles(1245632))->toBe(774)
        ->and(Distance::km(null))->toBeNull()
        ->and(Distance::miles(null))->toBeNull();
});
