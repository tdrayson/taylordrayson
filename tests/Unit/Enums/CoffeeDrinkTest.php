<?php

use App\Enums\CoffeeDrink;

it('reads real logged coffees as coffee', function (string $name) {
    expect(CoffeeDrink::matches($name))->toBeTrue();
})->with([
    'Caramel Oat Latte',
    'Millionaire’s Latte',
    'White Americano Skimmed milk',
    'Oat Milk Dirty Chai Latte',
    'Strawberries And Cream Frappuccino, Grande, Whole Milk',
    'Caramel Frappuccino 250ml Glass Bottle',
    'White Chocolate Frappucino',
    'Tropical Mango Bubble Frappé',
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
    'Frappe, Mint Milkshake, With Cream & Chocolate Sprinkles',
]);
