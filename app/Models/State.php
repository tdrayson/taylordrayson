<?php

namespace App\Models;

use App\Support\StateStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A single current value, overwritten in place and never charted. Anything with
 * history, or that you would filter or aggregate, belongs in its own typed table.
 * Read and write through {@see StateStore}, not this model.
 */
#[Fillable([
    'key',
    'value',
    'observed_at',
])]
class State extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
        ];
    }
}
