<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Reference enum of the words that mark a food log item as a coffee, NOT a cast.
 * Food names are free text, so an item is a coffee when its name contains any value.
 */
enum CoffeeDrink: string
{
    case Coffee = 'coffee';
    case Cappuccino = 'cappuccino';
    case Latte = 'latte';
    case Americano = 'americano';
    case Espresso = 'espresso';
    case FlatWhite = 'flat white';
    case Macchiato = 'macchiato';
    case Mocha = 'mocha';
    case Cortado = 'cortado';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Every lowercase match term.
     *
     * @return list<string>
     */
    public static function terms(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether a food name reads as a coffee.
     *
     * @param  string  $name  A food log item's name, in any case.
     */
    public static function matches(string $name): bool
    {
        return Str::contains(strtolower($name), self::terms());
    }
}
