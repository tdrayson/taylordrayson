<?php

namespace App\Actions\Fuel;

use App\Models\Fuel;
use Illuminate\Support\Facades\Artisan;

class QueueMissingBrandLogo
{
    /**
     * Queues the logo download for a fill-up's brand when the file is not on disk yet.
     */
    public function __invoke(Fuel $fuel): void
    {
        if ($fuel->brand && $fuel->logo_url === null) {
            Artisan::queue('fuel:brand-logos', ['brand' => [$fuel->brand]]);
        }
    }
}
