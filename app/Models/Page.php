<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasDynamicContent;
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
    use HasAttachments, HasDynamicContent;

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

    /**
     * The cover image in the card/lightbox payload shape shared with articles,
     * or null when none is attached.
     *
     * @return array{src: string, srcset: ?string, full: string}|null
     */
    public function coverPhoto(): ?array
    {
        $media = $this->getFirstMedia('cover');

        if ($media === null) {
            return null;
        }

        return [
            'src' => $media->getUrl('card'),
            'srcset' => $media->getSrcset('card') ?: null,
            'full' => $media->getUrl(),
        ];
    }

    /** Pages are matched by the slug catch-all, so they sit at the site root. */
    public function url(): string
    {
        return '/'.$this->slug;
    }
}
