<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
])]
class Tag extends Model
{
    /**
     * The raw pivot rows, so a count of everything carrying this tag can be
     * eager-loaded. The taggables themselves are reached from the other side.
     *
     * @return HasMany<Taggable, $this>
     */
    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class);
    }
}
