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

    /** The cross-type feed for everything carrying this tag. */
    public function url(): string
    {
        return self::urlFor($this->slug);
    }

    /** The same path for a slug the taxonomy registry knows without loading a row. */
    public static function urlFor(string $slug): string
    {
        return '/tags/'.$slug;
    }
}
