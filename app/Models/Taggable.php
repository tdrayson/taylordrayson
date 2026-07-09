<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polymorphic pivot binding a {@see Tag} to any taggable model. The table
 * carries no timestamps or surrogate key, only the morph triple.
 *
 * @property int $tag_id
 * @property string $taggable_type
 * @property int $taggable_id
 */
class Taggable extends Model
{
    public $timestamps = false;
}
