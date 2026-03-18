<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'assetable_type',
    'assetable_id',
    'type',
    'path',
    'original_filename',
    'width',
    'height',
    'mime_type',
    'size_bytes',
    'order',
])]
class Asset extends Model
{
    use HasFactory;

    public function assetable(): MorphTo
    {
        return $this->morphTo();
    }
}
