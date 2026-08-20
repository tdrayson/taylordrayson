<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Observers\LinkFaviconObserver;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

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
#[ObservedBy(LinkFaviconObserver::class)]
class Page extends Model implements HasMedia
{
    use HasAttachments;

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
