<?php

namespace App\Contracts;

use Illuminate\Database\Schema\Blueprint;

/**
 * Orbit-style: each content index model declares its SQLite cache columns.
 */
interface DefinesContentSchema
{
    public static function schema(Blueprint $table): void;
}
