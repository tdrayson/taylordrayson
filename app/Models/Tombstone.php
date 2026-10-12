<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The address of a deleted post, kept so it answers 410 Gone rather than 404.
 * Nothing of the post itself is kept: deleting it means the words are gone.
 */
#[Fillable(['path', 'deleted_at'])]
class Tombstone extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
