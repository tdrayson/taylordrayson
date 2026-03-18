<?php

namespace App\Models\Concerns;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasAssets
{
    public function assets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assetable');
    }

    public function cover(): MorphOne
    {
        return $this->morphOne(Asset::class, 'assetable')->where('type', 'cover');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assetable')->where('type', 'photo')->orderBy('order');
    }

    public function map(): MorphOne
    {
        return $this->morphOne(Asset::class, 'assetable')->where('type', 'map');
    }
}
