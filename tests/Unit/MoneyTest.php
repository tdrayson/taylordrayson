<?php

use App\Support\Money;

it('formats gbp to two places and pence per litre to a tenth', function () {
    expect(Money::gbp(50.6))->toBe('£50.60')
        ->and(Money::gbp(50))->toBe('£50.00')
        ->and(Money::gbp(null))->toBeNull()
        ->and(Money::pencePerLitre(1.619))->toBe('161.9p')
        ->and(Money::pencePerLitre(null))->toBeNull();
});
