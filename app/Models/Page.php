<?php

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A standalone, CP-managed content page (e.g. /sleep-score), rendered with the
 * article layout. Not a timeline entry: pages are routed by slug, not by date.
 */
#[Fillable([
    'title',
    'slug',
    'excerpt',
    'content',
    'published',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published' => 'boolean',
        ];
    }
}
