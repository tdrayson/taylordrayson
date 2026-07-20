<?php

namespace App\Enums;

enum CabinClass: string
{
    case Economy = 'economy';
    case PremiumEconomy = 'premium_economy';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Economy => 'Economy',
            self::PremiumEconomy => 'Premium economy',
            self::Business => 'Business',
        };
    }
}
