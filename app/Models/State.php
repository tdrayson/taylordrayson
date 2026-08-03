<?php

namespace App\Models;

use App\Support\StateStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A single current value, overwritten in place and never charted: the phone's
 * battery, the weather outside, today's rings so far. The key/value home for
 * server-side ambient state, distinct from the client-side `useSettings` store
 * of visitor preferences.
 *
 * Anything with history, or that you would filter, sort or aggregate across,
 * belongs in its own typed table instead. Reading and writing goes through
 * {@see StateStore} rather than this model directly.
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
