<?php

use App\Enums\CabinClass;

it('has exactly the three stored values (no "first")', function () {
    expect(array_column(CabinClass::cases(), 'value'))
        ->toBe(['economy', 'premium_economy', 'business']);
});

it('labels each case for display', function () {
    expect(CabinClass::Economy->label())->toBe('Economy')
        ->and(CabinClass::PremiumEconomy->label())->toBe('Premium economy')
        ->and(CabinClass::Business->label())->toBe('Business');
});

it('resolves from the stored backed value', function () {
    expect(CabinClass::from('premium_economy'))->toBe(CabinClass::PremiumEconomy);
});
