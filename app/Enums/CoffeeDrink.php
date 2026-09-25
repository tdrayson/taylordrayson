<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Reference enum of the words that mark a food log item as a coffee, NOT a cast.
 * Food names are free text, so an item is a coffee when its name contains a value
 * and none of the exclusions.
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
    case Frappe = 'frapp';

    // Logged foods that name a coffee without being one: BBQ Americano pizzas,
    // latte cake, Matchmakers, espresso martinis, shakes, protein drinks, Lion's
    // Mane. 'mane' not 'lion', which would drop Millionaire's Latte.
    private const EXCLUSIONS = ['bbq', 'pizza', 'cake', 'matchmakers', 'martini', 'shake', 'protein', 'mane'];

    public function label(): string
    {
        return match ($this) {
            self::Frappe => 'Frappe',
            default => ucfirst($this->value),
        };
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
     * Lowercase terms that disqualify a name even when it contains a coffee term.
     *
     * @return list<string>
     */
    public static function exclusions(): array
    {
        return self::EXCLUSIONS;
    }

    /**
     * Whether a food name reads as a coffee.
     *
     * @param  string  $name  A food log item's name, in any case.
     */
    public static function matches(string $name): bool
    {
        $name = mb_strtolower($name);

        return Str::contains($name, self::terms()) && ! Str::contains($name, self::EXCLUSIONS);
    }
}
