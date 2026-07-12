<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

#[Fillable([
    'name',
    'slug',
])]
class Tag extends Model implements DefinesContentSchema
{
    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    }

    public static function schemaTaggables(Blueprint $table): void
    {
        $table->unsignedBigInteger('tag_id');
        $table->string('taggable_type');
        $table->unsignedBigInteger('taggable_id');
        $table->index(['taggable_type', 'taggable_id']);
    }
}
