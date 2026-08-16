<?php

namespace App\Actions\Fuel;

use App\Models\Fuel;

class UpdateFuel
{
    public function __construct(private DeriveFuelFigures $derive) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Fuel $fuel, array $attributes): Fuel
    {
        // Re-derived off the row's own figures so the three never drift apart.
        $attributes = ($this->derive)($attributes, $fuel->only(['litres', 'cost', 'price_per_litre']));

        $fuel->fill($attributes)->save();

        return $fuel->refresh();
    }
}
