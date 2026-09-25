<?php

use App\Enums\CoffeeDrink;

it('reads real logged coffees as coffee', function (string $name) {
    expect(CoffeeDrink::matches($name))->toBeTrue();
})->with([
    'Caramel Oat Latte',
    'Millionaire’s Latte',
    'White Americano Skimmed milk',
    'Oat Milk Dirty Chai Latte',
]);

it('leaves out foods that only name a coffee', function (string $name) {
    expect(CoffeeDrink::matches($name))->toBeFalse();
})->with([
    'BBQ Americano Pizza (Pan)',
    'Pumpkin Spice Latte Decadent Cake',
    'Matchmakers Caramel Coffee',
    'Espresso Martini',
    'Coffee Milkshake',
    'Lion’s Mane Latte',
]);
