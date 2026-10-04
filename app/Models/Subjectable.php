<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polymorphic pivot binding a {@see Subject} to any subjectable model. The
 * table carries no timestamps or surrogate key, only the morph triple.
 *
 * @property int $subject_id
 * @property string $subjectable_type
 * @property int $subjectable_id
 */
class Subjectable extends Model
{
    public $timestamps = false;
}
