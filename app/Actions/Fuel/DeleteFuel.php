<?php

namespace App\Actions\Fuel;

use App\Models\Fuel;

class DeleteFuel
{
    public function __invoke(Fuel $fuel): void
    {
        $fuel->delete();
    }
}
