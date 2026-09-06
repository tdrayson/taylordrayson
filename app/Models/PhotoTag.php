<?php

namespace App\Models;

use App\Enums\PhotoTagRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `attachment_subject` pivot: which subject is tagged on which attachment,
 * its role, and its position for a subject role.
 */
class PhotoTag extends Pivot
{
    protected $table = 'attachment_subject';

    public $incrementing = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => PhotoTagRole::class,
            'x' => 'float',
            'y' => 'float',
        ];
    }
}
